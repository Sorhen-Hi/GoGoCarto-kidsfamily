<?php

namespace Biopen\GeoDirectoryBundle\EventListener;

use Biopen\GeoDirectoryBundle\Document\Element;
use Biopen\GeoDirectoryBundle\Document\ElementJsonOntology;
use Biopen\GeoDirectoryBundle\Document\ModerationState;
use Biopen\GeoDirectoryBundle\Document\ElementStatus;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\Router;

class ElementJsonGenerator
{
  protected $currElementChangeset;
  protected $config = null;
  protected $options = null;
  protected $semanticFields = [];
  protected $router = null;

  public function __construct(Router $router)
  {
      $this->router = $router;
  }

  public function getConfig($dm)
  {
    if (!$this->config) $this->config = $dm->getRepository('BiopenCoreBundle:Configuration')->findConfiguration();

    $formFields = $this->config->getElementFormFields();
    foreach( $formFields as $key => $field ) {
      if( isset($field->semantic) && $field->semantic != "" ) {
          $this->semanticFields[$field->name] = $field->semantic;
      }
    }

    return $this->config;
  }

  public function getOptions($dm)
  {
    // load all options so we don't need to do a query on each element being modified
    if (!$this->options) $this->options = $dm->getRepository('BiopenGeoDirectoryBundle:Option')->createQueryBuilder()
                                             ->select('name')->hydrate(false)->getQuery()->execute()->toArray();
    return $this->options;
  }

  public function preFlush(\Doctrine\ODM\MongoDB\Event\PreFlushEventArgs $eventArgs)
  {
    $dm = $eventArgs->getDocumentManager();
    $documentManaged = $dm->getUnitOfWork()->getIdentityMap();

    if (array_key_exists("Biopen\GeoDirectoryBundle\Document\Element", $documentManaged))
    {
      // dump("on pre flush, number of doc managed" . count($documentManaged["Biopen\GeoDirectoryBundle\Document\Element"]));
      // $uow = $dm->getUnitOfWork();
      // $uow->computeChangeSets();

      foreach ($documentManaged["Biopen\GeoDirectoryBundle\Document\Element"] as $key => $element)
      {
        if (!$element->getPreventJsonUpdate()) {
          $element->setPreventJsonUpdate(true); // ensure perofming serialization only once
          $element->checkForModerationStillNeeded();

          // if we want to update only some specific part of the Json object, user currElementChangeset and below method attrChanged
          // $this->currElementChangeset = array_keys($uow->getDocumentChangeSet($element));
          $this->updateJsonRepresentation($element, $dm);
        }
      }
    }
  }

  public function updateJsonRepresentation($element, $dm)
  {
    if (!$element->getGeo()) { return; }
    $config = $this->getConfig($dm);
    $options = $this->getOptions($dm);
    $privateProps = $config->getApi()->getPublicApiPrivateProperties();

    // -------------------- FULL JSON ----------------

    // BASIC FIELDS
    $baseJson = json_encode($element);
    $baseJson = substr($baseJson , 0, -1); // remove last '}'
    if ($element->getAddress())   $baseJson .= ', "address":'    . $element->getAddress()->toJson();
    if ($element->getOpenHours()) $baseJson .= ', "openHours": ' . $element->getOpenHours()->toJson();

    // CREATED AT, UPDATED AT
    $baseJson .= ', "createdAt":"'    . date_format($element->getCreatedAt(),"d/m/Y à H:i") . '"';
    $updatedAt = $element->getUpdatedAt() ? $element->getUpdatedAt() : $element->getCreatedAt();
    $updatedAtFormated = gettype($updatedAt) == "integer" ? date("d/m/Y à H:i", $updatedAt) : date_format($updatedAt,"d/m/Y à H:i");
    $baseJson .= ', "updatedAt":"'    . $updatedAtFormated . '"';

    // OPTIONS VALUES (= TAXONOMY)
    $sortedOptionsValues = $element->getSortedOptionsValues();
    $optValuesLength = count($sortedOptionsValues);
    $optionsString = '';
    $optionsFullJson = [];
    if ($sortedOptionsValues)
    {
      for ($i=0; $i < $optValuesLength; $i++) {
        $optionValue = $sortedOptionsValues[$i];
        if (isset($options[$optionValue->getOptionId()]))
        {
          $optionName = json_encode($options[$optionValue->getOptionId()]['name']);
          $optionsString .=  $optionName . ',';
          $optionsFullJson[] = $sortedOptionsValues[$i]->toJson($optionName);
        }
        else
        {
          $element->removeOptionValue($sortedOptionsValues[$i]);
        }
      }
    }
    $optionsString = rtrim($optionsString, ',');
    $baseJson .= ',"categories": [' . $optionsString . '],';
    $element->setOptionsString($optionsString); // we also update optionsString attribute which is used in exporting from element admin list
    // Options values with description
    if (count($optionsFullJson)) $baseJson .= '"categoriesFull": [' . implode(",", $optionsFullJson) . '],';

    // CUSTOM DATA
    if ($element->getData())
        foreach ($element->getData() as $key => $value) {
            $baseJson .= '"'. $key .'": ' . json_encode($value) . ',';
        }

    // SPECIFIC DATA
    $baseJson .= $this->encodeArrayObjectToJson("stamps", $element->getStamps());
    $imagesJson = $this->encodeArrayObjectToJson("images", $element->getImages());
    $filesJson  = $this->encodeArrayObjectToJson("files", $element->getFiles());
    if (!in_array('images', $privateProps)) $baseJson .= $imagesJson;
    if (!in_array('files', $privateProps))  $baseJson .= $filesJson;
    $baseJson = rtrim($baseJson, ',');

    // MODIFIED ELEMENT (for pending modification)
    if ($element->getModifiedElement()) {
        $baseJson .= ', "modifiedElement": ' . $element->getModifiedElement()->getJson(ElementJsonOntology::Full, true, false);
    }
    $baseJson .= '}';

    $element->setBaseJson($baseJson);


    // -------------------- PRIVATE JSON -------------------------
    $privateJson = '{';
    // status
    $status = strval($element->getStatus());
    if (!$status || $status == "" || strlen($status) == 0) $status = "0";
    $privateJson .= '"status": ' . $status . ',';
    $privateJson .= '"moderationState": ' . $element->getModerationState() . ',';
    // CUSTOM PRIVATE DATA
    foreach ($element->getPrivateData() as $key => $value) {
        $privateJson .= '"'. $key .'": ' . json_encode($value) . ',';
    }
    if (in_array('images', $privateProps)) $privateJson .= $imagesJson;
    if (in_array('files', $privateProps))  $privateJson .= $filesJson;
    $privateJson = rtrim($privateJson, ',');
    $privateJson .= '}';
    $element->setPrivateJson($privateJson);


    // ---------------- ADMIN JSON = REPORTS & CONTRIBUTIONS ---------------------
    $adminJson = '{';
    if ($element->getStatus() != ElementStatus::ModifiedPendingVersion)
    {
        $adminJson .= $this->encodeArrayObjectToJson('reports', $element->getUnresolvedReports());
        $adminJson .= $this->encodeArrayObjectToJson('contributions', $element->getContributionsAndResolvedReports());
        if ($element->isPending()) {
            $adminJson .= $this->encodeArrayObjectToJson('votes', $element->getVotesArray());
            if ($element->getCurrContribution()) $adminJson .= '"pendingContribution":' . $element->getCurrContribution()->toJson();
        }
        $adminJson = rtrim($adminJson, ',');
    }
    $adminJson .= '}';
    $element->setAdminJson($adminJson);

    // -------------------- COMPACT JSON ----------------
    // [id, customData, latitude, longitude, status, moderationState]
    $compactFields = $config->getMarker()->getFieldsUsedByTemplate();
    $compactData = [];
    foreach ($compactFields as $field) $compactData[] = $element->getProperty($field);

    $compactJson = '["'.$element->id . '",' . json_encode($compactData) . ',';
    $compactJson.= $element->getGeo()->getLatitude() .','. $element->getGeo()->getLongitude() .', [';
    if ($sortedOptionsValues)
    {
        for ($i=0; $i < $optValuesLength; $i++) {
            $value = $sortedOptionsValues[$i];
            $compactJson .= $value->getOptionId();
            $compactJson .= ',';
        }
        $compactJson = rtrim($compactJson, ',');
    }
    $compactJson .= ']';
    if ($element->getStatus() <= 0 || $element->getModerationState() != 0) {
      $compactJson .= ','. $status;
    }
    if ($element->getModerationState() != 0) $compactJson .= ','. $element->getModerationState();
    $compactJson .= ']';
    $element->setCompactJson($compactJson);

    // -------------------- SEMANTIC JSON ----------------

    $semanticJson = "{";
    $semanticJson .= '"@id": "' . $this->router->generate('biopen_api_element_get', array('id'=>$element->id, '_format'=>'jsonld'), UrlGeneratorInterface::ABSOLUTE_URL) . '",';

    if ($element->getData()) {
      foreach ($element->getData() as $key => $value) {
          if( array_key_exists($key, $this->semanticFields) && $value ) {
              $semanticJson .= '"' . $this->semanticFields[$key] . '": ' . json_encode($value) . ',';
          }
      }
    }

    $semanticJson = rtrim($semanticJson, ',');
    $semanticJson .= '}';

    $element->setSemanticJson($semanticJson);
  }

  // private function attrChanged($attrs)
  // {
  //   if (!$this->currElementChangeset) return true;
  //   foreach ($attrs as $attr) {
  //       if (in_array($attr, $this->currElementChangeset)) return true;
  //   }
  //   return false;
  // }

  private function encodeArrayObjectToJson($propertyName, $array)
  {
    if (!$array) return "";
    $array = is_array($array) ? $array : $array->toArray();
    if (count($array) == 0) return '';
    $result = '"'. $propertyName .'": [';
    foreach ($array as $key => $value) {
        $result .= $value->toJson();
        $result .= ',';
    }
    $result = rtrim($result, ',');
    $result .= '],';
    return $result;
  }
}
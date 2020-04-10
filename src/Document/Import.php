<?php

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use Gedmo\Mapping\Annotation as Gedmo;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Vich\UploaderBundle\Mapping\Annotation as Vich;

abstract class ImportState
{
    const Started = 'started';
    const Downloading = 'downloading';
    const InProgress = 'in_progress';
    const Completed = 'completed';
    const Errors = 'errors';
    const Failed = 'failed';
}

/**
 * @MongoDB\Document
 * @Vich\Uploadable
 * Import data into GoGoCarto. the data can imported through a static file, or via API url
 * The Import can be made once for all (static import) or dynamically every X days (ImportDynamic)
 *
 * @MongoDB\InheritanceType("SINGLE_COLLECTION")
 * @MongoDB\DiscriminatorField("type")
 * @MongoDB\DiscriminatorMap({"normal"="Import", "dynamic"="ImportDynamic"})
 */
class Import extends AbstractFile
{
    protected $vichUploadFileKey = 'import_file';

    /**
     * @var int
     * @MongoDB\Id(strategy="INCREMENT")
     */
    private $id;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    public $sourceName;

    /**
     * @var string
     *             Url of API to get the data
     * @MongoDB\Field(type="string")
     */
    private $url;

    /**
     * @MongoDB\ReferenceOne(targetDocument="App\Document\Category", cascade={"persist"})
     */
    private $parentCategoryToCreateOptions = null;

    /**
     * @MongoDB\ReferenceMany(targetDocument="App\Document\Option", cascade={"persist"})
     */
    private $optionsToAddToEachElement = [];

    /**
     * @MongoDB\Field(type="bool")
     */
    private $createMissingOptions = false;

    /**
     * @MongoDB\Field(type="bool")
     */
    private $needToHaveOptionsOtherThanTheOnesAddedToEachElements = false;

    /**
     * @MongoDB\Field(type="bool")
     */
    private $preventImportIfNoCategories = false;

    /**
     * @var string
     * @MongoDB\Field(type="string")
     */
    public $fieldToCheckElementHaveBeenUpdated;

    /**
     * @MongoDB\Field(type="bool")
     */
    private $geocodeIfNecessary = false;

    /**
     * @MongoDB\ReferenceMany(targetDocument="App\Document\GoGoLog", cascade={"all"})
     */
    private $logs;

    /**
     * State of the import when processing. Type of ImportState
     * When processing import, this variable is being updated in realtime, so the client can follow
     * the state of the import also in realtime.
     *
     * @MongoDB\Field(type="string")
     */
    private $currState;

    /**
     * A message can be added to the state information.
     *
     * @MongoDB\Field(type="string")
     */
    private $currMessage;

    /**
     * After importing some Data, if the user hard delete some elements, their ids will be remembered
     * so next time we do not import them again.
     *
     * @MongoDB\Field(type="collection")
     */
    private $idsToIgnore = [];

    /**
     * @MongoDB\Field(type="hash")
     */
    private $ontologyMapping = [];

    /**
     * @MongoDB\Field(type="bool")
     */
    private $newOntologyToMap = false;

    /**
     * @MongoDB\Field(type="hash")
     */
    private $taxonomyMapping = [];

    /**
     * @MongoDB\Field(type="bool")
     */
    private $newTaxonomyToMap = false;

    /**
     * Custom code made by the user to be run on the $data object when importing.
     *
     * @MongoDB\Field(type="string")
     */
    private $customCode = '<?php';

    /**
     * @var date
     *
     * @MongoDB\Field(type="date")
     */
    private $lastRefresh = null;

    /**
     * @MongoDB\Field(type="date")
     * @Gedmo\Timestampable(on="create")
     */
    private $createdAt;

    /**
     * @MongoDB\Field(type="date")
     * @Gedmo\Timestampable(on="update")
     */
    private $updatedAt;

    public function __construct()
    {
        $this->logs = new \Doctrine\Common\Collections\ArrayCollection();
    }

    public function __toString()
    {
        return 'Import '.$this->sourceName;
    }

    public function isDynamicImport()
    {
        return false;
    }

    public function addIdToIgnore($id)
    {
        $this->idsToIgnore[] = $id;
    }

    public function isCategoriesFieldMapped()
    {
        return $this->getOntologyMapping() ? in_array('categories', array_values($this->getOntologyMapping())) : false;
    }

    /**
     * @Assert\Callback
     */
    public function validate(ExecutionContextInterface $context)
    {
        if (preg_match('/new |process|mongo|this|symfony|exec|passthru|shell_exec|system|proc_open|popen|curl_exec|curl_multi_exec|parse_ini_file|show_source|var_dump|print_r/i', $this->customCode)) {
            $context->buildViolation("Il est interdit d'utiliser les mots suivants: new , process, mongo, this, symfony, exec, passthru, shell_exec, system, proc_open, popen, curl_exec, curl_multi_exec, parse_ini_file, show_source, var_dump, print_r... Merci de ne pas faire de betises !")
                ->atPath('customCode')
                ->addViolation();
        }
    }

    /**
     * Get id.
     *
     * @return int_id $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set parentCategoryToCreateOptions.
     *
     * @param App\Document\Category $parentCategoryToCreateOptions
     *
     * @return $this
     */
    public function setParentCategoryToCreateOptions(\App\Document\Category $parentCategoryToCreateOptions)
    {
        $this->parentCategoryToCreateOptions = $parentCategoryToCreateOptions;

        return $this;
    }

    /**
     * Get parentCategoryToCreateOptions.
     *
     * @return App\Document\Category $parentCategoryToCreateOptions
     */
    public function getParentCategoryToCreateOptions()
    {
        return $this->parentCategoryToCreateOptions;
    }

    /**
     * Set url.
     *
     * @param string $url
     *
     * @return $this
     */
    public function setUrl($url)
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get url.
     *
     * @return string $url
     */
    public function getUrl()
    {
        return $this->url;
    }

    /**
     * Set createMissingOptions.
     *
     * @param bool $createMissingOptions
     *
     * @return $this
     */
    public function setCreateMissingOptions($createMissingOptions)
    {
        $this->createMissingOptions = $createMissingOptions;

        return $this;
    }

    /**
     * Get createMissingOptions.
     *
     * @return bool $createMissingOptions
     */
    public function getCreateMissingOptions()
    {
        return $this->createMissingOptions;
    }

    /**
     * Set geocodeIfNecessary.
     *
     * @param bool $geocodeIfNecessary
     *
     * @return $this
     */
    public function setGeocodeIfNecessary($geocodeIfNecessary)
    {
        $this->geocodeIfNecessary = $geocodeIfNecessary;

        return $this;
    }

    /**
     * Get geocodeIfNecessary.
     *
     * @return bool $geocodeIfNecessary
     */
    public function getGeocodeIfNecessary()
    {
        return $this->geocodeIfNecessary;
    }

    /**
     * Add optionsToAddToEachElement.
     *
     * @param App\Document\Option $optionsToAddToEachElement
     */
    public function addOptionsToAddToEachElement(\App\Document\Option $optionsToAddToEachElement)
    {
        $this->optionsToAddToEachElement[] = $optionsToAddToEachElement;
    }

    /**
     * Remove optionsToAddToEachElement.
     *
     * @param App\Document\Option $optionsToAddToEachElement
     */
    public function removeOptionsToAddToEachElement(\App\Document\Option $optionsToAddToEachElement)
    {
        $this->optionsToAddToEachElement->removeElement($optionsToAddToEachElement);
    }

    /**
     * Get optionsToAddToEachElement.
     *
     * @return \Doctrine\Common\Collections\Collection $optionsToAddToEachElement
     */
    public function getOptionsToAddToEachElement()
    {
        return $this->optionsToAddToEachElement;
    }

    /**
     * Set sourceName.
     *
     * @param string $sourceName
     *
     * @return $this
     */
    public function setSourceName($sourceName)
    {
        $this->sourceName = $sourceName;

        return $this;
    }

    /**
     * Get sourceName.
     *
     * @return string $sourceName
     */
    public function getSourceName()
    {
        return $this->sourceName;
    }

    /**
     * Add log.
     *
     * @param App\Document\GoGoLog $log
     */
    public function addLog(\App\Document\GoGoLog $log)
    {
        $this->logs[] = $log;
    }

    /**
     * Remove log.
     *
     * @param App\Document\GoGoLog $log
     */
    public function removeLog(\App\Document\GoGoLog $log)
    {
        $this->logs->removeElement($log);
    }

    /**
     * Get logs.
     *
     * @return \Doctrine\Common\Collections\Collection $logs
     */
    public function getLogs()
    {
        $logs = is_array($this->logs) ? $this->logs : $this->logs->toArray();
        usort($logs, function ($a, $b) { return $b->getCreatedAt()->getTimestamp() - $a->getCreatedAt()->getTimestamp(); });

        return $logs;
    }

    /**
     * Set currState.
     *
     * @param string $currState
     *
     * @return $this
     */
    public function setCurrState($currState)
    {
        $this->currState = $currState;

        return $this;
    }

    /**
     * Get currState.
     *
     * @return string $currState
     */
    public function getCurrState()
    {
        return $this->currState;
    }

    /**
     * Set currMessage.
     *
     * @param string $currMessage
     *
     * @return $this
     */
    public function setCurrMessage($currMessage)
    {
        $this->currMessage = $currMessage;

        return $this;
    }

    /**
     * Get currMessage.
     *
     * @return string $currMessage
     */
    public function getCurrMessage()
    {
        return $this->currMessage;
    }

    /**
     * Set idsToIgnore.
     *
     * @param collection $idsToIgnore
     *
     * @return $this
     */
    public function setIdsToIgnore($idsToIgnore)
    {
        $this->idsToIgnore = $idsToIgnore;

        return $this;
    }

    /**
     * Get idsToIgnore.
     *
     * @return collection $idsToIgnore
     */
    public function getIdsToIgnore()
    {
        return $this->idsToIgnore;
    }

    /**
     * Set needToHaveOptionsOtherThanTheOnesAddedToEachElements.
     *
     * @param bool $needToHaveOptionsOtherThanTheOnesAddedToEachElements
     *
     * @return $this
     */
    public function setNeedToHaveOptionsOtherThanTheOnesAddedToEachElements($needToHaveOptionsOtherThanTheOnesAddedToEachElements)
    {
        $this->needToHaveOptionsOtherThanTheOnesAddedToEachElements = $needToHaveOptionsOtherThanTheOnesAddedToEachElements;

        return $this;
    }

    /**
     * Get needToHaveOptionsOtherThanTheOnesAddedToEachElements.
     *
     * @return bool $needToHaveOptionsOtherThanTheOnesAddedToEachElements
     */
    public function getNeedToHaveOptionsOtherThanTheOnesAddedToEachElements()
    {
        return $this->needToHaveOptionsOtherThanTheOnesAddedToEachElements;
    }

    /**
     * Set fieldToCheckElementHaveBeenUpdated.
     *
     * @param string $fieldToCheckElementHaveBeenUpdated
     *
     * @return $this
     */
    public function setFieldToCheckElementHaveBeenUpdated($fieldToCheckElementHaveBeenUpdated)
    {
        $this->fieldToCheckElementHaveBeenUpdated = $fieldToCheckElementHaveBeenUpdated;

        return $this;
    }

    /**
     * Get fieldToCheckElementHaveBeenUpdated.
     *
     * @return string $fieldToCheckElementHaveBeenUpdated
     */
    public function getFieldToCheckElementHaveBeenUpdated()
    {
        return $this->fieldToCheckElementHaveBeenUpdated;
    }

    /**
     * Set ontologyMapping.
     *
     * @param hash $ontologyMapping
     *
     * @return $this
     */
    public function setOntologyMapping($ontologyMapping)
    {
        $this->ontologyMapping = $ontologyMapping;

        return $this;
    }

    /**
     * Get ontologyMapping.
     *
     * @return hash $ontologyMapping
     */
    public function getOntologyMapping()
    {
        return $this->ontologyMapping;
    }

    /**
     * Set taxonomyMapping.
     *
     * @param hash $taxonomyMapping
     *
     * @return $this
     */
    public function setTaxonomyMapping($taxonomyMapping)
    {
        $this->taxonomyMapping = $taxonomyMapping;

        return $this;
    }

    /**
     * Get taxonomyMapping.
     *
     * @return hash $taxonomyMapping
     */
    public function getTaxonomyMapping()
    {
        return $this->taxonomyMapping;
    }

    /**
     * Set customCode.
     *
     * @param string $customCode
     *
     * @return $this
     */
    public function setCustomCode($customCode)
    {
        $this->customCode = $customCode;

        return $this;
    }

    /**
     * Get customCode.
     *
     * @return string $customCode
     */
    public function getCustomCode()
    {
        return $this->customCode;
    }

    /**
     * Set lastRefresh.
     *
     * @param date $lastRefresh
     *
     * @return $this
     */
    public function setLastRefresh($lastRefresh)
    {
        $this->lastRefresh = $lastRefresh;

        return $this;
    }

    /**
     * Get lastRefresh.
     *
     * @return date $lastRefresh
     */
    public function getLastRefresh()
    {
        return $this->lastRefresh;
    }

    /**
     * Set createdAt.
     *
     * @param date $createdAt
     *
     * @return $this
     */
    public function setCreatedAt($createdAt)
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get createdAt.
     *
     * @return date $createdAt
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set newOntologyToMap.
     *
     * @param bool $newOntologyToMap
     *
     * @return $this
     */
    public function setNewOntologyToMap($newOntologyToMap)
    {
        $this->newOntologyToMap = $newOntologyToMap;

        return $this;
    }

    /**
     * Get newOntologyToMap.
     *
     * @return bool $newOntologyToMap
     */
    public function getNewOntologyToMap()
    {
        return $this->newOntologyToMap;
    }

    /**
     * Set newTaxonomyToMap.
     *
     * @param bool $newTaxonomyToMap
     *
     * @return $this
     */
    public function setNewTaxonomyToMap($newTaxonomyToMap)
    {
        $this->newTaxonomyToMap = $newTaxonomyToMap;

        return $this;
    }

    /**
     * Get newTaxonomyToMap.
     *
     * @return bool $newTaxonomyToMap
     */
    public function getNewTaxonomyToMap()
    {
        return $this->newTaxonomyToMap;
    }

    /**
     * Set preventImportIfNoCategories.
     *
     * @param bool $preventImportIfNoCategories
     *
     * @return $this
     */
    public function setPreventImportIfNoCategories($preventImportIfNoCategories)
    {
        $this->preventImportIfNoCategories = $preventImportIfNoCategories;

        return $this;
    }

    /**
     * Get preventImportIfNoCategories.
     *
     * @return bool $preventImportIfNoCategories
     */
    public function getPreventImportIfNoCategories()
    {
        return $this->preventImportIfNoCategories;
    }
}
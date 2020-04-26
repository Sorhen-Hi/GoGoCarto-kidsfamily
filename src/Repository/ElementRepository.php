<?php

/**
 * This file is part of the GoGoCarto project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2018-06-17 16:46:33
 */

namespace App\Repository;

use App\Document\ElementStatus;
use App\Document\ModerationState;
use Doctrine\ODM\MongoDB\DocumentRepository;

/**
 * ElementRepository.
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class ElementRepository extends DocumentRepository
{
    public function findDuplicatesFor($element)
    {
        // Duplicates search is used in two places :
        // 1- When we create a new element, we check that it's not always existing
        // 2- From the bulk action detect duplicates. It goes through all the database to find duplicates
        // For newly created element the search is wider, because we look also on deleted element, and
        // it's okay for the user to just say "no this is new element"
        // For the bulk detection, we cannot make a wider query otherwise it could result in thousands of
        // duplicates to proceed
        $forNewlyCreatedElement = $element->getId() == null;
        $forBulkDuplicateDetection = !$forNewlyCreatedElement;

        $qb = $this->createQueryBuilder('App\Document\Element');

        // GEO SPATIAL QUERY
        if ($forNewlyCreatedElement) {
            $distance = 1;
        } else {
            $distance = 0.4;
            $city = strtolower($element->getAddress()->getAddressLocality());
            if (in_array($element->getAddress()->getDepartmentCode(), ['75', '92', '93', '94'])
                || in_array($city, ['marseille', 'lyon', 'bordeaux', 'lille', 'montpellier', 'strasbourg', 'nantes', 'nice'])) {
                $distance = 0.1;
            }
        }
        $radius = $distance / 110; // convert kilometre in degrees
        $qb->field('geo')->withinCenter((float) $element->getGeo()->getLatitude(), (float) $element->getGeo()->getLongitude(), $radius);

        // REDUCE SCOPE FOR BULK DETECTION
        if ($forBulkDuplicateDetection) {
            $qb->field('status')->gt(ElementStatus::PendingModification);
            $qb->field('moderationState')->notEqual(ModerationState::PotentialDuplicate);
            $qb->field('id')->notIn($element->getNonDuplicatesIds());
        }

        // FILTER BY TEXT SEARCH
        $this->queryText($qb, $element->getName());

        $qb->limit(6);

        return $this->queryToArray($qb);
    }

    public function findWhithinBoxes($bounds, $request, $getFullRepresentation, $isAdmin = false)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');

        $status = ('false' === $request->get('pendings')) ? ElementStatus::AdminValidate : ElementStatus::PendingModification;
        $this->filterVisibles($qb, $status);

        // get elements within box
        foreach ($bounds as $key => $bound) {
            if (4 == count($bound)) {
                $qb->addOr($qb->expr()->field('geo')->withinBox((float) $bound[1], (float) $bound[0], (float) $bound[3], (float) $bound[2]));
            }
        }

        if ($request) {
            $this->filterWithRequest($qb, $request);
        }
        $this->selectJson($qb, $getFullRepresentation, $isAdmin);

        // execute request
        $results = $this->queryToArray($qb);

        return $results;
    }

    // When user want to proceed already detected duplicates by the bulk action
    // We use the field "duplicate node" to find them
    public function findDuplicatesNodes($limit = null, $getCount = null)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');
        $qb->field('isDuplicateNode')->equals(true);
        if ($getCount) {
            $qb->count();
        } else {
            $qb->field('lockUntil')->lte(time());
            if ($limit) {
                $qb->limit($limit);
            }
        }

        return $qb->getQuery()->execute();
    }


    public function findElementsWithText($text, $fullRepresentation = true, $isAdmin = false)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');

        $this->queryText($qb, $text);
        $this->filterVisibles($qb);

        $this->selectJson($qb, $fullRepresentation, $isAdmin);

        return $this->queryToArray($qb);
    }

    public function findPendings($getCount = false)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');

        $qb->field('status')->in([ElementStatus::PendingAdd, ElementStatus::PendingModification]);
        if ($getCount) {
            $qb->count();
        }

        return $qb->getQuery()->execute();
    }

    public function findModerationNeeded($getCount = false, $moderationState = null)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');

        if (null != $moderationState) {
            $qb->field('moderationState')->equals($moderationState);
        } else {
            $qb->field('moderationState')->notIn([ModerationState::NotNeeded]);
        }
        $qb->field('status')->gte(ElementStatus::PendingModification);

        if ($getCount) {
            $qb->count();
        }

        return $qb->getQuery()->execute();
    }

    public function findValidated($getCount = false)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');

        $qb->field('status')->gt(ElementStatus::PendingAdd)->field('status')->notEqual(ElementStatus::DynamicImport);
        if ($getCount) {
            $qb->count();
        }

        return $qb->getQuery()->execute();
    }

    public function findVisibles($getCount = false, $excludeImported = false, $limit = null, $skip = null)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');

        $qb = $this->filterVisibles($qb);
        if ($excludeImported) {
            $qb->field('status')->notEqual(ElementStatus::DynamicImport);
        }
        if ($limit) {
            $qb->limit($limit);
        }
        if ($skip) {
            $qb->skip($skip);
        }
        if ($getCount) {
            $qb->count();
        }

        return $qb->getQuery()->execute();
    }

    public function findAllPublics($getFullRepresentation, $isAdmin, $request = null)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');

        $qb = $this->filterVisibles($qb);
        $qb->field('moderationState')->equals(ModerationState::NotNeeded);

        if ($request) {
            $this->filterWithRequest($qb, $request);
        }
        $this->selectJson($qb, $getFullRepresentation, $isAdmin);

        return $this->queryToArray($qb);
    }

    public function findAllElements($limit = null, $skip = null, $getCount = false)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');

        if ($limit) {
            $qb->limit($limit);
        }
        if ($skip) {
            $qb->skip($skip);
        }
        if ($getCount) {
            $qb->count();
        }

        return $qb->getQuery()->execute();
    }

    private function queryToArray($qb)
    {
        return $qb->hydrate(false)->getQuery()->execute()->toArray();
    }

    private function filterWithRequest($qb, $request)
    {
        $categoriesIds = $request->get('categories');
        if ($categoriesIds) {
            if (!is_array($categoriesIds)) {
                $categoriesIds = explode(',', $categoriesIds);
            }
            $categoriesIds = array_map(function ($el) { return (float) $el; }, $categoriesIds);
            $qb->field('optionValues.optionId')->in($categoriesIds);
        }

        if ($request->get('excludeExternal')) {
            $qb->field('status')->notEqual(ElementStatus::DynamicImport);
        }

        $stampsIds = $request->get('stampsIds');
        if ($stampsIds) {
            if (!is_array($stampsIds)) {
                $stampsIds = explode(',', $stampsIds);
            }
            $qb->field('stamps.id')->in($stampsIds);
        }

        $limit = $request->get('limit');
        if ($limit && $limit > 0) {
            $qb->limit($limit);
        }
    }

    private function queryText($qb, $text)
    {
        $config = $this->getDocumentManager()->getRepository('App\Document\Configuration')->findConfiguration();
        if ($config->getSearchExcludingWords()) {
            $text = $text.' --'.str_replace(',', ' --', $config->getSearchExcludingWords());
        }
        return $qb->text($text)->sortMeta('score', 'textScore');
    }

    private function filterVisibles($qb, $status = ElementStatus::PendingModification)
    {
        // fetching pendings and validated
        $qb->field('status')->gte($status);
        // removing element withtout category or withtout geolocation
        $qb->field('moderationState')->notIn([ModerationState::GeolocError, ModerationState::NoOptionProvided]);

        return $qb;
    }

    private function selectJson($qb, $getFullRepresentation, $isAdmin)
    {
        // get json representation
        if ('true' == $getFullRepresentation) {
            $qb->select(['baseJson', 'privateJson']);
            if ($isAdmin) {
                $qb->select('adminJson');
            }
        } else {
            $qb->select('compactJson');
        }
    }

    public function findElementsOwnedBy($userEmail)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');

        $qb->field('userOwnerEmail')->equals($userEmail);
        $qb->field('status')->notEqual(ElementStatus::ModifiedPendingVersion);
        $qb->sort('updatedAt', 'DESC');

        return $qb->getQuery()->execute();
    }

    // Used by newsletter
    public function findWithinCenterFromDate($lat, $lng, $distance, $date, $limit = null)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');
        $radius = $distance / 110;
        $qb->field('geo')->withinCenter((float) $lat, (float) $lng, $radius);
        $qb->field('createdAt')->gt($date);
        $qb = $this->filterVisibles($qb);
        if ($limit) {
            $qb->limit($limit);
        }

        return $qb->getQuery()->execute();
    }

    public function findStampedWithId($stampId)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');
        $qb->field('stamps.id')->in([(float) $stampId]);
        $qb->select('id');

        return $this->queryToArray($qb);
    }

    public function findPotentialDuplicateOwner($element)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');
        $qb->field('potentialDuplicates')->includesReferenceTo($element);

        return $qb->getQuery()->execute();
    }

    public function findOriginalElementOfModifiedPendingVersion($element)
    {
        $qb = $this->createQueryBuilder('App\Document\Element');
        $qb->field('modifiedElement')->references($element);

        return $qb->getQuery()->getSingleResult();
    }

    public function findDataCustomProperties()
    {
        return array_merge($this->findPublicCustomProperties(), $this->findPrivateCustomProperties());
    }

    public function findPublicCustomProperties()
    {
        return $this->findProperties('this.data');
    }

    public function findPrivateCustomProperties()
    {
        return $this->findProperties('this.privateData');
    }

    private function findProperties($rootPath = 'this')
    {
        $qb = $this->createQueryBuilder('App\Document\Element')
      ->map('function() { for (var key in '.$rootPath.') { emit(key, null); } }')
      ->reduce('function(k, vals) { return null; }');

        return array_map(function ($array) { return $array['_id']; }, $qb->getQuery()->execute()->toArray());
    }

    public function findAllCustomProperties($onlyPublic = false)
    {
        $dataProperties = $onlyPublic ? $this->findPublicCustomProperties() : $this->findDataCustomProperties();
        $allProperties = [];
        foreach ($dataProperties as $prop) {
            $allProperties[] = $prop;
        }

        $formProperties = $this->findFormProperties();

        return array_merge($allProperties, $formProperties);
    }

    public function findFormProperties()
    {
        $formProperties = [];
        $propTypeToIgnore = ['separator', 'header', 'address', 'title', 'email', 'taxonomy', 'openhours'];
        $config = $this->getDocumentManager()->getRepository('App\Document\Configuration')->findConfiguration();
        foreach ($config->getElementFormFields() as $key => $field) {
            if (property_exists($field, 'name') && !in_array($field->type, $propTypeToIgnore)) {
                $formProperties[] = $field->name;
            }
        }

        return $formProperties;
    }

    public function findDeletedElementsByImportIdCount()
    {
        $builder = $this->createAggregationBuilder('App\Document\Element');
        $builder
          ->match()
            ->field('status')->lte(ElementStatus::AdminRefused)
          ->group()
              ->field('_id')
              ->expression('$source')
              ->field('count')
              ->sum(1)
        ;
        $queryResult = $builder->execute();
        $result = [];
        foreach ($queryResult as $key => $value) {
            $result[$value['_id']['$id']] = $value['count'];
        }

        return $result;
    }
}

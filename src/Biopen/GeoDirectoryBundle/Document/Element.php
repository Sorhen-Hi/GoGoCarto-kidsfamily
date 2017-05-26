<?php

/**
 * This file is part of the MonVoisinFaitDuBio project.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright Copyright (c) 2016 Sebastian Castro - 90scastro@gmail.com
 * @license    MIT License
 * @Last Modified time: 2017-05-26 17:05:06
 */
 

namespace Biopen\GeoDirectoryBundle\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation\Expose;
use Gedmo\Mapping\Annotation as Gedmo;

abstract class ElementStatus
{
    const ModifiedPendingVersion = -5;
    const Deleted = -4;
    const CollaborativeRefused = -3;
    const AdminRefused = -2;    
    const PendingModification = -1;
    const PendingAdd = 0;
    const AdminValidate = 1;
    const CollaborativeValidate = 2;
    const AddedByAdmin = 3; 
    const ModifiedByAdmin = 4;            
}

abstract class ModerationState
{
    const NotNeeded = 0;
    const ReportsSubmitted = 1;
    const VotesConflicts = 2;      
}

/**
 * Element
 *
 * @MongoDB\Document(repositoryClass="Biopen\GeoDirectoryBundle\Repository\ElementRepository")
 * @MongoDB\HasLifecycleCallbacks 
 * @MongoDB\Index(keys={"coordinates"="2d"})
 * @MongoDB\Index(keys={"name"="text"})
 * @Gedmo\Loggable
 */
class Element
{
    /**
     * @var int
     *  
     * @MongoDB\Id(strategy="ALNUM") 
     */
    public $id;

    /** 
     * @Expose
     * @MongoDB\Field(type="int")
     */
    public $status;

    /** 
     * @Expose
     * If element need moderation we write here the type of modification needed
     * @MongoDB\Field(type="int")
     */
    private $moderationState = 0;

    /**
     * @var \stdClass
     *
     * @MongoDB\EmbedMany(targetDocument="Biopen\GeoDirectoryBundle\Document\UserInteraction")
     */
    private $votes;

    /**
     * @var \stdClass
     *
     * @MongoDB\EmbedMany(targetDocument="Biopen\GeoDirectoryBundle\Document\UserInteraction")
     */
    private $reports;

    /**
     * @var \stdClass
     *
     * @MongoDB\EmbedOne(targetDocument="Biopen\GeoDirectoryBundle\Document\Element")
     */
    private $modifiedElement;

    /**
     * @var string
     * @Expose
     * @Gedmo\Versioned
     * @MongoDB\Field(type="string")
     */
    public $name;

    /** 
    * @Expose
    * @Gedmo\Versioned
    * @MongoDB\EmbedOne(targetDocument="Biopen\GeoDirectoryBundle\Document\Coordinates") 
    */
    public $coordinates;

    /**
     * @var string
     * @Expose     
     * @Gedmo\Versioned
     * @MongoDB\Field(type="string")
     */
    public $address;

    /**
     * @var string
     *
     * @Gedmo\Versioned
     * @MongoDB\Field(type="string")
     */
    private $postalCode;

    /**
     * @var string
     *
     * @Gedmo\Versioned
     * @MongoDB\Field(type="string")
     */
    private $departementCode;

    /**
     * @var string
     * @Expose
     * @Gedmo\Versioned
     * @MongoDB\Field(type="string", nullable=false)
     */
    public $description;

    /**
     * @var string
     * @Expose
     * @Gedmo\Versioned
     * @MongoDB\Field(type="string")
     */
    public $tel;

    /**
     * @var string
     * @Expose
     * @Gedmo\Versioned
     * @MongoDB\Field(type="string")
     */
    public $mail;

    /**
     * @var string
     * @Expose
     * @Gedmo\Versioned
     * @MongoDB\Field(type="string")
     */
    public $webSite;
    
    /**
     * @var \stdClass
     * @Expose
     * @MongoDB\EmbedMany(targetDocument="Biopen\GeoDirectoryBundle\Document\OptionValue")
     */
    private $optionValues;

    /**
     * @var \stdClass
     * @Expose
     * @Gedmo\Versioned
     * @MongoDB\EmbedOne(targetDocument="Biopen\GeoDirectoryBundle\Document\OpenHours")
     */
    public $openHours;

    /**
     * @var string
     * @Expose
     * @Gedmo\Versioned
     * @MongoDB\Field(type="string", nullable=true)
     */
    public $openHoursMoreInfos;

   /**
     * @var string
     * 
     * @Gedmo\Versioned
     * @MongoDB\Field(type="string")
     */
    private $contributorMail;

    /**
     * @Gedmo\Versioned
     * @MongoDB\Field(type="bool")
     */
    private $contributorIsRegisteredUser;

    /** 
     * @var string 
     * 
     * @MongoDB\Field(type="string") 
     */ 
    private $compactJson; 

    /** 
     * @var string 
     * 
     * @MongoDB\Field(type="string") 
     */ 
    private $fullJson; 

    /**
     * @var date $createdAt
     *
     * @MongoDB\Date
     * @Gedmo\Timestampable(on="create")
     */
    private $createdAt;

    /**
     * @var date $updatedAt
     *
     * @MongoDB\Date
     * @Gedmo\Timestampable
     */
    private $updatedAt;


    /**
     * Constructor
     */
    public function __construct()
    {
        
    }

    public function resetOptionsValues()
    {
        $this->optionValues = [];
    }

    /** @MongoDB\PreFlush */
    public function updateJsonRepresentation()
    {
        if (!$this->optionValues || !$this->coordinates) return;

        $fullJson = json_encode($this);
        $fullJson = rtrim($fullJson,'}');
        $fullJson .= ', "optionValues": [';

        foreach ($this->optionValues as $key => $value) {
            $fullJson .= '{ "optionId" :'.$value->getOptionId().', "index" :'.$value->getIndex();
            if ($value->getDescription()) $fullJson .=  ', "description" : "' . $value->getDescription() . '"';
            $fullJson .= '}';
            if ($key != count($this->optionValues) -1) $fullJson .= ',';
        }
        $fullJson .= ']';
        if ($this->getModifiedElement()) $fullJson .= ', "modifiedElement": ' . $this->getModifiedElement()->getFullJson();
        $fullJson .= '}';
        
        $this->setFullJson($fullJson);  

        $compactJson = '["'.$this->id . '",' .$this->status . ',"' .$this->name . '",'. $this->coordinates->getLat() .','. $this->coordinates->getLng().', [';
        foreach ($this->optionValues as $key => $value) {
            $compactJson .= '['.$value->getOptionId().','.$value->getIndex();
            //if ($value->getDescription()) $responseJson .=  ',' . $value->getDescription();
            $compactJson .= ']';
            if ($key != count($this->optionValues) -1) $compactJson .= ',';
        }
        $compactJson .= ']]';
        $this->setCompactJson($compactJson);
        //$this->json = 'changed from prePersist callback! ID = ' . $this->id;
    }

    public function isPending()
    {
        return $this->status == ElementStatus::PendingAdd || $this->status == ElementStatus::PendingModification;
    }

    public function __toString() {
        return $this->getName();
    }

    /**
     * Get id
     *
     * @return custom_id $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Get id
     *
     * @return custom_id $id
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Set name
     *
     * @param string $name
     * @return $this
     */
    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    /**
     * Get name
     *
     * @return string $name
     */
    public function getName()
    {
        return $this->name;
    }


    /**
     * Set address
     *
     * @param string $address
     * @return $this
     */
    public function setAddress($address)
    {
        $this->address = $address;
        return $this;
    }

    /**
     * Get address
     *
     * @return string $address
     */
    public function getAddress()
    {
        return $this->address;
    }

    /**
     * Set postalCode
     *
     * @param string $postalCode
     * @return $this
     */
    public function setPostalCode($postalCode)
    {
        $this->postalCode = $postalCode;
        $this->setDepartementCode(substr($postalCode, 0, 2));
        return $this;
    }

    /**
     * Get postalCode
     *
     * @return string $postalCode
     */
    public function getPostalCode()
    {
        return $this->postalCode;
    }
    
    /**
     * Set postalCode
     *
     * @param string $postalCode
     * @return $this
     */
    public function setDepartementCode($code)
    {
        $this->departementCode = $code;
        return $this;
    }

    /**
     * Get postalCode
     *
     * @return string $postalCode
     */
    public function getDepartementCode()
    {
        return $this->departementCode;
    }

    /**
     * Set description
     *
     * @param string $description
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get description
     *
     * @return string $description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set tel
     *
     * @param string $tel
     * @return $this
     */
    public function setTel($tel)
    {
        $this->tel = $tel;
        return $this;
    }

    /**
     * Get tel
     *
     * @return string $tel
     */
    public function getTel()
    {
        return $this->tel;
    }

    /**
     * Set mail
     *
     * @param string $mail
     * @return $this
     */
    public function setMail($mail)
    {
        $this->mail = $mail;
        return $this;
    }

    /**
     * Get mail
     *
     * @return string $mail
     */
    public function getMail()
    {
        return $this->mail;
    }

    /**
     * Set webSite
     *
     * @param string $webSite
     * @return $this
     */
    public function setWebSite($webSite)
    {
        $this->webSite = $webSite;
        return $this;
    }

    /**
     * Get webSite
     *
     * @return string $webSite
     */
    public function getWebSite()
    {
        return $this->webSite;
    }

    /**
     * Set categories
     *
     * @param object_id $categories
     * @return $this
     */
    public function setCategories($categories)
    {
        $this->categories = $categories;
        return $this;
    }

    /**
     * Get categories
     *
     * @return object_id $categories
     */
    public function getCategories()
    {
        return $this->categories;
    }

    /**
     * Set openHours
     *
     * @param object_id $openHours
     * @return $this
     */
    public function setOpenHours($openHours)
    {
        $this->openHours = $openHours;
        return $this;
    }

    /**
     * Get openHours
     *
     * @return object_id $openHours
     */
    public function getOpenHours()
    {
        return $this->openHours;
    }

    /**
     * Set openHoursMoreInfos
     *
     * @param string $openHoursMoreInfos
     * @return $this
     */
    public function setOpenHoursMoreInfos($openHoursMoreInfos)
    {
        $this->openHoursMoreInfos = $openHoursMoreInfos;
        return $this;
    }

    /**
     * Get openHoursMoreInfos
     *
     * @return string $openHoursMoreInfos
     */
    public function getOpenHoursMoreInfos()
    {
        return $this->openHoursMoreInfos;
    }


    /**
     * Set contributorMail
     *
     * @param string $contributorMail
     * @return $this
     */
    public function setContributorMail($contributorMail)
    {
        $this->contributorMail = $contributorMail;
        return $this;
    }

    /**
     * Get contributorMail
     *
     * @return string $contributorMail
     */
    public function getContributorMail()
    {
        return $this->contributorMail;
    }

    /**
     * Set validationCode
     *
     * @param string $validationCode
     * @return $this
     */
    public function setValidationCode($validationCode)
    {
        $this->validationCode = $validationCode;
        return $this;
    }

    /**
     * Get validationCode
     *
     * @return string $validationCode
     */
    public function getValidationCode()
    {
        return $this->validationCode;
    }


    /**
     * Add optionValue
     *
     * @param Biopen\GeoDirectoryBundle\Document\OptionValue $optionValue
     */
    public function addOptionValue(\Biopen\GeoDirectoryBundle\Document\OptionValue $optionValue)
    {
        $this->optionValues[] = $optionValue;
    }

    /**
     * Remove optionValue
     *
     * @param Biopen\GeoDirectoryBundle\Document\OptionValue $optionValue
     */
    public function removeOptionValue(\Biopen\GeoDirectoryBundle\Document\OptionValue $optionValue)
    {
        $this->optionValues->removeElement($optionValue);
    }

    /**
     * Get optionValues
     *
     * @return \Doctrine\Common\Collections\Collection $optionValues
     */
    public function getOptionValues()
    {
        return $this->optionValues;
    }

    /**
     * Set status
     *
     * @param int $status
     * @return $this
     */
    public function setStatus($status)
    {
        $this->status = $status;
        return $this;
    }

    /**
     * Get status
     *
     * @return int $status
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * Add vote
     *
     * @param Biopen\GeoDirectoryBundle\Document\Vote $vote
     */
    public function addVote(\Biopen\GeoDirectoryBundle\Document\UserInteraction $vote)
    {
        $this->votes[] = $vote;
    }

    /**
     * Remove vote
     *
     * @param Biopen\GeoDirectoryBundle\Document\Vote $vote
     */
    public function removeVote(\Biopen\GeoDirectoryBundle\Document\UserInteraction $vote)
    {
        $this->votes->removeElement($vote);
    }

    /**
     * Get votes
     *
     * @return \Doctrine\Common\Collections\Collection $votes
     */
    public function getVotes()
    {
        return $this->votes;
    }

    /**
     * Set contributorIsRegisteredUser
     *
     * @param bool $contributorIsRegisteredUser
     * @return $this
     */
    public function setContributorIsRegisteredUser($contributorIsRegisteredUser)
    {
        $this->contributorIsRegisteredUser = $contributorIsRegisteredUser;
        return $this;
    }

    /**
     * Get contributorIsRegisteredUser
     *
     * @return bool $contributorIsRegisteredUser
     */
    public function getContributorIsRegisteredUser()
    {
        return $this->contributorIsRegisteredUser;
    }


    /**
     * Set coordinates
     *
     * @param Biopen\GeoDirectoryBundle\Document\Coordinates $coordinates
     * @return $this
     */
    public function setCoordinates(\Biopen\GeoDirectoryBundle\Document\Coordinates $coordinates)
    {
        $this->coordinates = $coordinates;
        return $this;
    }

    /**
     * Get coordinates
     *
     * @return Biopen\GeoDirectoryBundle\Document\Coordinates $coordinates
     */
    public function getCoordinates()
    {
        return $this->coordinates;
    }



    /**
     * Set compactJson
     *
     * @param string $compactJson
     * @return $this
     */
    public function setCompactJson($compactJson)
    {
        $this->compactJson = $compactJson;
        return $this;
    }

    /**
     * Get compactJson
     *
     * @return string $compactJson
     */
    public function getCompactJson()
    {
        return $this->compactJson;
    }

    /**
     * Set fullJson
     *
     * @param string $fullJson
     * @return $this
     */
    public function setFullJson($fullJson)
    {
        $this->fullJson = $fullJson;
        return $this;
    }

    /**
     * Get fullJson
     *
     * @return string $fullJson
     */
    public function getFullJson()
    {
        return $this->fullJson;
    }

    /**
     * Add report
     *
     * @param Biopen\GeoDirectoryBundle\Document\Report $report
     */
    public function addReport(\Biopen\GeoDirectoryBundle\Document\UserInteraction $report)
    {
        $this->reports[] = $report;
        $this->setModerationState(ModerationState::ReportsSubmitted);
    }

    /**
     * Remove report
     *
     * @param Biopen\GeoDirectoryBundle\Document\Report $report
     */
    public function removeReport(\Biopen\GeoDirectoryBundle\Document\UserInteraction $report)
    {
        $this->reports->removeElement($report);
    }

    /**
     * Get reports
     *
     * @return \Doctrine\Common\Collections\Collection $reports
     */
    public function getReports()
    {
        return $this->reports;
    }

    /**
     * Set created
     *
     * @param date $created
     * @return $this
     */
    public function setCreatedAt($created)
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Get created
     *
     * @return date $created
     */
    public function getCreatedAt()
    {
        return $this->createdAt;
    }

    /**
     * Set updated
     *
     * @param date $updated
     * @return $this
     */
    public function setUpdatedAt($updated)
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Get updated
     *
     * @return date $updated
     */
    public function getUpdatedAt()
    {
        return $this->updatedAt;
    }

    /**
     * Set statusMessage
     *
     * @param string $statusMessage
     * @return $this
     */
    public function setModerationState($moderationState)
    {
        $this->moderationState = $moderationState;
        return $this;
    }

    /**
     * Get statusMessage
     *
     * @return string $statusMessage
     */
    public function getModerationState()
    {
        return $this->moderationState;
    }

    /**
     * Set modifiedElement
     *
     * @param Biopen\GeoDirectoryBundle\Document\Element $modifiedElement
     * @return $this
     */
    public function setModifiedElement(\Biopen\GeoDirectoryBundle\Document\Element $modifiedElement)
    {
        $this->modifiedElement = $modifiedElement;
        return $this;
    }

    /**
     * Get modifiedElement
     *
     * @return Biopen\GeoDirectoryBundle\Document\Element $modifiedElement
     */
    public function getModifiedElement()
    {
        return $this->modifiedElement;
    }
}

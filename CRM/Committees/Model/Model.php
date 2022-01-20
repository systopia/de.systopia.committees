<?php
/*-------------------------------------------------------+
| SYSTOPIA Committee Framework                           |
| Copyright (C) 2021 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+--------------------------------------------------------*/

/**
 * The model containing all entities
 */
class CRM_Committees_Model_Model
{
    /** @var array list of committees, indexed by its ID */
    protected $committees = [];

    /** @var array list of persons, indexed by its ID */
    protected $persons = [];

    /** @var array list of committee memberships, indexed by its ID */
    protected $memberships = [];

    /** @var array list of addresses, indexed by its ID */
    protected $addresses = [];

    /** @var array list of phones, indexed by its ID */
    protected $phones = [];

    /** @var array list of emails, indexed by its ID */
    protected $emails = [];

    /**
     * Add a new person to the model
     *
     * Possible attributes:
     *  'id'           => committee ID
     *  'first_name'   => full name of the committee
     *  'last_name'    => short name of the committee
     *  'formal_title' => short handle, external ID
     *
     * @param array|CRM_Committees_Model_Person $data
     *    the committee data
     *
     * @return CRM_Committees_Model_Person
     */
    public function addPerson($data)
    {
        // todo: validation?
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Person($this, $data);
        }
        $this->persons[$data->getID()] = $data;
        return $data;
    }

    /**
     * Add a new committee as a data record.
     * Possible attributes:
     *  'id'         => committee ID
     *  'name'       => full name of the committee
     *  'name_short' => short name of the committee
     *  'handle'     => short handle, external ID
     *  'start_date' => date when the committee was/will be created
     *  'end_date'   => date when the committee was/will be terminated
     * @param array|CRM_Committees_Model_Committee $data
     *    the committee data
     *
     * @return CRM_Committees_Model_Committee
     */
    public function addCommittee($data)
    {
        // todo: validation
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Committee($this, $data);
        }
        // todo: validation?
        $this->committees[$data->getID()] = $data;
        return $data;
    }

    /**
     * Add a new address as to the model
     *
     * Possible attributes:
     *  'id'              => address ID
     *  'street_address'  => street address
     *  'postal_code'     => postal code
     *  'city'            => city
     *
     * @param array|CRM_Committees_Model_Address $data
     */
    public function addAddress($data)
    {
        // todo: validation
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Address($this, $data);
        }
        // todo: validation?
        $this->addresses[$data->getID()] = $data;
        return $data;
    }

    /**
     * Add a new address as to the model
     *
     * Possible attributes:
     *  'id'              => email ID
     *  'contact_id'      => person or organisation ID
     *  'email'           => email address
     *  'type'            => email type
     *
     * @param array|CRM_Committees_Model_Email $data
     */
    public function addEmail($data)
    {
        // todo: validation
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Email($this, $data);
        }
        // todo: validation?
        $this->emails[$data->getID()] = $data;
        return $data;
    }

    /**
     * Add a new address as to the model
     *
     * Possible attributes:
     *  'id'              => email ID
     *  'contact_id'      => person or organisation ID
     *  'phone'           => phone number
     *  'type'            => email type
     *
     * @param array|CRM_Committees_Model_Phone $data
     */
    public function addPhone($data)
    {
        // todo: validation
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Phone($this, $data);
        }
        // todo: validation?
        $this->phones[$data->getID()] = $data;
        return $data;
    }

    /**
     * Add a new address as to the model
     *
     * Possible attributes:
     *
     *  'id'              => email ID
     *  'contact_id'      => person or organisation ID
     *  'committee_id'    => id of a committee
     *  'title'           => membership title
     *  'represents'      => Organisation name?
     *  'start_date'      => when did the membership start?
     *  'end_date'        => when did the membership end?
     *
     * @param array|CRM_Committees_Model_Membership $data
     */
    public function addCommitteeMembership($data)
    {
        // todo: validation
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Membership($this, $data);
        }
        $data->validate();
        $this->memberships[$data->getID()] = $data;
        return $data;
    }


    /**
     * Get a membership with the given ID
     *
     * @param string $id
     *   the ID
     *
     * @return CRM_Committees_Model_Membership $data
     */
    public function getCommitteeMembership($id)
    {
        return $this->memberships[$id] ?? null;
    }

    /**
     * Get a committee with the given ID
     *
     * @param string $id
     *   the ID
     *
     * @return CRM_Committees_Model_Committee $data
     */
    public function getCommittee($id)
    {
        return $this->committees[$id] ?? null;
    }

    /**
     * Get a person with the given ID
     *
     * @param string $id
     *   the ID
     *
     * @return CRM_Committees_Model_Person $data
     */
    public function getPerson($id)
    {
        return $this->persons[$id] ?? null;
    }

    /**
     * Get a list of all committees
     *
     * @return array
     */
    public function getAllCommittees()
    {
        return $this->committees;
    }

    /**
     * Get a list of all committees
     *
     * @return array
     */
    public function getAllPersons()
    {
        return $this->persons;
    }

    /**
     * Get a list of all committees
     *
     * @return array
     */
    public function getAllMemberships()
    {
        return $this->memberships;
    }

    /**
     * Get a list of all addresses
     *
     * @return array
     */
    public function getAllAddresses()
    {
        return $this->addresses;
    }


    /**
     * Join the data of the 'other_entities' into the 'entities' on the two fields
     *
     * @param array $entities
     *   the entities that should be extended, i.e. will be added
     *
     * @param array $other_entities
     *    the other entities where the data is taken from
     *
     * @param string $other_id_field
     *    the field the carries the ID to be joined
     *
     * @param string $entity_id_field
     *    the field the carries the other ID to be joined
     *
     * @param array $fields
     *    list of fields to be copied. default is all (except pre-existing ones)
     */
    public function join(&$entities, $other_entities, $other_id_field = 'contact_id', $entity_id_field = 'id', $fields = null)
    {
        // create an index of the other entities
        $other_entities_indexed = [];
        foreach ($other_entities as $other_entity)
        {
            /** @var CRM_Committees_Model_Entity $other_entity */
            $other_entity_link = $other_entity->getAttribute($other_id_field);
            if (isset($other_entities_indexed[$other_entity_link])) {
                throw new Exception("Key field {$other_id_field} is not unique");
            } else {
                $other_entities_indexed[$other_entity_link] = $other_entity;
            }
        }

        // now join the data
        foreach ($entities as &$entity) {
            /** @var CRM_Committees_Model_Entity $entity */
            $key = $entity->getAttribute($entity_id_field);
            if (!isset($key)) {
                throw new Exception("Key field {$entity_id_field} is empty");
            }

            if (isset($other_entities_indexed[$key])) {
                /** @var CRM_Committees_Model_Entity $other_entity */
                $other_entity = $other_entities_indexed[$key];
                $attributes = $fields ?? $other_entity->getFields();
                foreach ($attributes as $attribute) {
                    if ($attribute != $entity_id_field && $attribute != $other_id_field) {
                        $entity->setAttribute($attribute, $other_entity->getAttribute($attribute));
                    }
                }
            }
        }
    }

    /**
     * Join all the address data to the persons via the contact_id attribute
     *
     * @throws \Exception
     */
    public function joinAddressesToPersons()
    {
        $this->join($this->persons, $this->addresses, 'contact_id', 'id');
    }

    /**
     * Join all the email data to the persons via the contact_id attribute
     *
     * @throws \Exception
     */
    public function joinEmailsToPersons()
    {
        $this->join($this->persons, $this->emails, 'contact_id', 'id', ['email']);
    }

    /**
     * Join all the phone data to the persons via the contact_id attribute
     *
     * @throws \Exception
     */
    public function joinPhonesToPersons()
    {
        $this->join($this->persons, $this->phones, 'contact_id', 'id', ['phone']);
    }
}
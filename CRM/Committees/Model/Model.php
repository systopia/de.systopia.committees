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

    /** @var array list of addresses, indexed by its ID */
    protected $addresses = [];

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
        $this->committees[$data->getID()] = $data;
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
        $this->committees[$data->getID()] = $data;
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
        $this->committees[$data->getID()] = $data;
        return $data;
    }
}
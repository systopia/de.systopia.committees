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

    /** @var array list of committees, indexed by it's ID */
    protected $committees = [];

    /** @var array list of persons, indexed by it's ID */
    protected $persons = [];

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
     */
    public function addPerson($data)
    {
        // todo: validation?
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Person($data);
        }
        $this->persons[$data->getID()] = $data;
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
     */
    public function addCommittee($data)
    {
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Committee($data);
        }
        // todo: validation?
        $this->committees[$data->getID()] = $data;
    }
}
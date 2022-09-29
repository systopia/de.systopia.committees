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
 * The committee membership entity represents the assignment of a person to a committee
 */
class CRM_Committees_Model_Membership extends CRM_Committees_Model_Entity
{
    /**
     * Create a new object with the data
     *
     * @param CRM_Committees_Model_Model $model
     *    the model this entity belongs to
     *
     * @param array $data
     *  data as a named array of attributes
     */
    public function __construct($model, $data)
    {
        if (!isset($data['is_active'])) {
            // set is_active as default, if omitted
            $data['is_active'] = "1";
        }
        parent::__construct($model, $data);
    }


    /**
     * Validate the given values
     *
     * @throws Exception
     */
    public function validate()
    {
        // make sure the entities are in the model?
    }

    /**
     * Get the committee
     *
     * @return CRM_Committees_Model_Committee
     */
    public function getCommittee()
    {
        $committee_id = $this->getAttribute('committee_id');
        return $this->model->getCommittee($committee_id);
    }

    /**
     * Get the committee
     *
     * @return CRM_Committees_Model_Person
     */
    public function getPerson()
    {
        $contact_id = $this->getAttribute('contact_id');
        return $this->model->getPerson($contact_id);
    }
}
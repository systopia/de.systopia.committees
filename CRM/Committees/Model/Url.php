<?php
/*-------------------------------------------------------+
| SYSTOPIA Committee Framework                           |
| Copyright (C) 2022 SYSTOPIA                            |
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
 * The url entity represents any URL based link or reference
 */
class CRM_Committees_Model_Url extends CRM_Committees_Model_Entity
{
    const URL_TYPE_WEBSITE      = 'website';
    const URL_TYPE_SM_TWITTER   = 'twitter';
    const URL_TYPE_SM_FACBOOK   = 'facebook';
    const URL_TYPE_SM_INSTAGRAM = 'instagram';

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
        return parent::__construct($model, $data);
    }

    /**
     * Validate the given values
     *
     * @throws CRM_Committees_Model_ValidationException
     */
    public function validate()
    {
        // check if not empty
        if (!isset($this->attributes['url'])) {
            throw new CRM_Committees_Model_ValidationException($this, "Attribute 'url' is empty.");
        }
        // check if valid
        if (!filter_var($this->attributes['url'], FILTER_VALIDATE_URL)) {
            throw new CRM_Committees_Model_ValidationException($this, "Attribute 'url' is not a valid url.");
        }
    }
}
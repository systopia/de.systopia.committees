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
 * The committee entity represents a committee member
 */
class CRM_Committees_Model_Email extends CRM_Committees_Model_Entity
{
    /** @var string model property to force all emails to be lower case */
    const MODEL_PROPERTY_EMAIL_LOWER_CASE = 'MODEL_PROPERTY_EMAIL_LOWER_CASE';

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
        // enforce email lower case if enabled
        if ($model->getProperty(self::MODEL_PROPERTY_EMAIL_LOWER_CASE)) {
            if (isset($data['email'])) {
                $data['email'] = strtolower($data['email']);
            }
        }
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
        if (!isset($this->attributes['email'])) {
            throw new CRM_Committees_Model_ValidationException($this, "Attribute 'email' is empty.");
        }
        // check if valid
        if (!filter_var($this->attributes['email'], FILTER_VALIDATE_EMAIL)) {
            throw new CRM_Committees_Model_ValidationException($this, "Attribute 'email' is not a valid email.");
        }
    }
}
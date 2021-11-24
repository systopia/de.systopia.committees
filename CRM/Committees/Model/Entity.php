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
 * Base entity of the Committee model
 */
abstract class CRM_Committees_Model_Entity
{
    /** @var \CRM_Committees_Model_Model */
    protected $model;

    /** @var string $id */
    protected $id;

    /** @var array $attributes */
    protected $attributes;

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
        $this->model = $model;
        $this->attributes = $data;
        if (isset($data['id'])) {
            $this->id = $data['id'];
        } else {
            $this->id = $this->generateID();
        }
    }

    /**
     * Validate the given values
     *
     * @throws Exception
     */
    public function validate()
    {
        // overwrite to implement
    }

    /**
     * Get the entity's ID
     *
     * @return string ID
     */
    public function getID()
    {
        return $this->id;
    }

    /**
     * Get all data from the entity
     *
     * @return array data
     */
    public function getData()
    {
        return $this->attributes;
    }

    /**
     * Get an attribute of the entity
     *
     * @param string $attribute_name
     *
     * @return string value
     */
    public function getAttribute($attribute_name)
    {
        return $this->attributes[$attribute_name] ?? null;
    }

    /**
     * Get an attribute of the entity
     *
     * @param string $attribute_name
     *
     * @param string $value
     */
    public function setAttribute(string $attribute_name, string $value)
    {
        $this->attributes[$attribute_name] = $value;
    }

    /**
     * Generate unique ID
     */
    public function generateID()
    {
        // todo: this is a quick&dirty implementation -> fix that
        static $last_id = 1;
        return $last_id++;
    }
}
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
     * Get all data from the entity,
     *   except for the attributes in hte list
     *
     * @param array $strip_attributes
     *   list of attribute to be stripped from the result
     *
     * @return array data
     */
    public function getDataWithout($strip_attributes)
    {
        $data = $this->attributes;
        foreach ($strip_attributes as $strip_attribute) {
            unset($data[$strip_attribute]);
        }
        return $data;
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

    /**
     * Get the list of fields that the entity currently has
     *
     * @param bool $include_id
     *   should the id field be included?
     */
    public function getFields()
    {
        return array_keys($this->attributes);
    }

    /**
     * Diff the attributes of this entity against another one
     *
     * @param $entity CRM_Committees_Model_Entity
     *   an entity
     *
     * @param $ignore_attributes array
     *   list of entities to be ignored
     *
     * @return array
     *  [attribute => [this entity value, other entity value]
     *
     */
    public function diff(CRM_Committees_Model_Entity $entity, array $ignore_attributes = [])
    {
        $diff = [];
        $this_entity_data = $this->getData();
        $other_entity_data = $entity->getData();

        // @todo the following can probably be implemented more efficiently
        $attributes = array_merge($this_entity_data, $other_entity_data);
        foreach ($ignore_attributes as $attribute) {
            unset($attributes[$attribute]);
        }
        $attributes = array_keys($attributes);

        // @todo the following can probably be implemented more efficiently
        foreach ($attributes as $attribute) {
            $this_value = $this_entity_data[$attribute] ?? null;
            $other_value = $other_entity_data[$attribute] ?? null;
            if ($this_value !== $other_value) {
                $diff[$attribute] = [$this_value, $other_value];
            }
        }

        return $diff;
    }

    /**
     * Get the person/committee as linked by the attribute contact_id,
     *   if it is part of the model
     *
     * @param CRM_Committees_Model_Model|null $model
     *   the model from which to take the entity. Default is *this one*
     *
     * @return CRM_Committees_Model_Entity
     *
     */
    public function getContact($model = null)
    {
        if (!$model) $model = $this->model;
        $contact_id = $this->getAttribute('contact_id');

        // try the person first
        $person = $model->getPerson($contact_id);
        if ($person) {
            return $person;
        }

        // try a committee
        $committee = $model->getCommittee($contact_id);
        if ($committee) {
            return $committee;
        }

        // nothing found
        return null;
    }
}
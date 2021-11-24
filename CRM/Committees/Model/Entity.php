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
    /** @var string $id */
    protected $id;

    /** @var array $attributes */
    protected $attributes;

    /**
     * Create a new object with the data
     * @param array $data
     *  indexed data
     */
    public function __construct($data)
    {
        $this->id = $data['id'];
        $this->attributes = $data;
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
}
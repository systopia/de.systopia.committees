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
 * This represents a failed validation
 */
class CRM_Committees_Model_ValidationException extends CRM_Core_Exception
{
    /** @var \CRM_Committees_Model_Entity  */
    protected $entity = null;

    public function __construct($message, $entity)
    {
        $this->entity = $entity;
        parent::__construct($message);
    }

}
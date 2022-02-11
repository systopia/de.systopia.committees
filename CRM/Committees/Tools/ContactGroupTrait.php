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

use CRM_Committees_ExtensionUtil as E;

/**
 * This trait adds features based on contact groups.
 *
 * Careful: the caching only works if all group_contact operations are performed through this trait
 */
trait CRM_Committees_Tools_ContactGroupTrait
{
    /** @var array list of contact ids indexed by group_id */
    protected $trait_group_members = [];

    /**
     * Init the group cache for the give group ID
     *
     * @param integer $group_id
     */
    protected function initGroupCache($group_id) {
        if (!isset($this->trait_group_members[$group_id])) {
            $group_id = (int) $group_id;
            $query = CRM_Core_DAO::executeQuery("SELECT contact_id FROM civicrm_group_contact WHERE group_id = {$group_id} AND status = 'Added';");
            while ($query->fetch()) {
                $this->trait_group_members[$group_id][$query->contact_id] = $query->contact_id;
            }
        }
    }

    /**
     * Add contact to the group
     *
     * @param integer $contact_id
     * @param integer $group_id
     * @param boolean $cached
     *   if caching is used, the adding an already existing contact will be cheaper
     *
     * @return void
     */
    public function addContactToGroup($contact_id, $group_id, $cached = false)
    {
        if ($cached) {
            $this->initGroupCache($group_id);
            if (isset($this->trait_group_members[$group_id][$contact_id])) {
                return; // already a member
            }
        }

        $this->callApi3('GroupContact', 'create', [
            'contact_id' => $contact_id,
            'group_id'  => $group_id
        ]);

        if ($cached) {
            $this->trait_group_members[$group_id][$contact_id] = $contact_id;
        }
    }
}
<?php
/*-------------------------------------------------------+
| SYSTOPIA Committee Framework                           |
| Copyright (C) 2025 SYSTOPIA                            |
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
 * This trait adds features based on contact tags.
 */
trait CRM_Committees_Tools_ContactTagTrait
{
    /**
     * Update the given tag for the given contact group,
     *  i.e. removes the tag from contacts that are not in the list
     *   and adds the tag to all contacts in the list that don't have it yet
     *
     * @param int $tag_id
     *   internal name of the tag
     *
     * @param array $contact_ids
     *   list of contact IDs
     *
     * @return void
     *
     */
    public function synchronizeTag(int $tag_id, array $contact_ids)
    {
        // @todo: implement
    }

    /**
     * Make sure that the tag exists, creating it if it's not there yet
     *
     * @param string $tag_name
     *   internal name of the tag
     *
     * @param string|null $tag_label
     *    label of the tag.
     *    will only be applied if the tag does not exist and will be created.
     *
     * @param string|null $tag_description
     *    description of the tag.
     *    will only be applied if the tag does not exist and will be created.
     *
     * @return int
     *   tag ID
     */
    public function createTagIfNotExists(string $tag_name, string $tag_label = null, string $tag_description = null) : int
    {
        // look up if it exists and return tag ID

        // if not: create one
        if (empty($tag_label)) {
            $tag_label = $tag_name;
        }

        // @todo: implement
    }

    /**
     * Update the given tag for the given contact group
     *
     * @param string $tag_name
     *   internal name of the tag
     *
     * @return void
     *
     */
    public function deleteTagIfExists(string $tag_name)
    {
        // @todo: implement
    }
}
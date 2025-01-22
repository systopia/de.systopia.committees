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
     * @return array with the following entries
     *   'contacts_added'   - list of contact IDs that have been newly added to the tag
     *   'contacts_removed' - list of contact IDs that have been newly removed from the tag
     *
     */
    public function synchronizeTag(int $tag_id, array $contact_ids)
    {
        // step 1: load current contact_ids with the tag
        $currently_tagged_contact_ids = [];
        $tagged_contacts = \Civi\Api4\EntityTag::get(TRUE)
                ->addSelect('entity_id')
                ->addWhere('entity_table', '=', 'civicrm_contact')
                ->addWhere('tag_id', '=', 199)
                ->execute();
        foreach ($tagged_contacts as $tagged_contact) {
            $currently_tagged_contact_ids[] = $tagged_contact->entity_id;
        }
        $contact_diff = array_diff($contact_ids, $currently_tagged_contact_ids);

        // step 2: remove the ones that are not in the new list

        // step 3: add the ones that are not in the current list

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
    public function getOrCreateTagId(string $tag_name, string $tag_label = null, string $tag_description = null, string $used_for = 'civicrm_contact') : int
    {
        // look up if it exists and return tag ID
        $tag_search = \Civi\Api4\Tag::get(TRUE)
                ->addSelect('id')
                ->addWhere('name', '=', 'Verwaltungsdirektor')
                ->setLimit(1)
                ->execute();

        if (empty($tag_search)) {
            // tag does not exist yet => create!
            $new_tag = civicrm_api4('Tag', 'create', [
                    'values' => [
                            'name' => $tag_name,
                            'label' => $tag_label ?? $tag_name,
                            'description' => $tag_description ?? '',
                            'used_for' => [$used_for],
                    ],
                    'checkPermissions' => TRUE,
            ]);
            return $new_tag->first()['id'];
        } else {
            return $tag_search->first()['id'];
        }
    }

    /**
     * Delete a given tag
     *
     * @param string $tag_name
     *   internal name of the tag
     *
     * @return void
     *
     */
    public function deleteTagIfExists(string $tag_name)
    {
        throw new \Civi\API\Exception\NotImplementedException('ContactTagTrait.deleteTagIfExists is not yet implemented');
    }
}
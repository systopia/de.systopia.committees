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
     * Tag contact with the given tag.
     *   If the tag is already there nothing happens
     *
     * @param int $contact_id
     *   contact ID
     * @param int $tag_id
     *   tag ID
     *
     * @return void
     */
    public function tagContact($contact_id, $tag_id, $tag_name = null)
    {
        try {
            \Civi\Api4\EntityTag::create(false)
                    ->addValue('tag_id', $tag_id)
                    ->addValue('entity_table', 'civicrm_contact')
                    ->addValue('entity_id', $contact_id)
                    ->execute();
            if ($tag_name) {
                $this->log("Tagged contact [{$contact_id}] with tag '{$tag_name}' [{$tag_id}].");
            } else {
                $this->log("Tagged contact [{$contact_id}] with tag [{$tag_id}].");
            }
        } catch (Exception $e) {
            // probably already there - ignore
        }
    }

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
    public function synchronizeTag(int $tag_id, array $contact_ids, $tag_name = null) : array
    {
        // step 0: look up tag name if necessary
        if (empty($tag_name)) {
            $tag_data = \Civi\Api4\Tag::get(false)
                    ->addSelect('name')
                    ->addWhere('id', '=', $tag_id)
                    ->execute()
                    ->first();
            $tag_name = $tag_data['name'];
        }

        // step 1: load current contact_ids with the tag
        $currently_tagged_contact_ids = [];
        $tagged_contacts = \Civi\Api4\EntityTag::get(false)
                ->addSelect('entity_id')
                ->addWhere('entity_table', '=', 'civicrm_contact')
                ->addWhere('tag_id', '=', $tag_id)
                ->execute();
        foreach ($tagged_contacts as $tagged_contact) {
            $currently_tagged_contact_ids[] = $tagged_contact['entity_id'];
        }

        // and then calculate the difference between the should-be vs. the current)
        $contact_diff = self::arrayDifference($contact_ids, $currently_tagged_contact_ids);
        $insertion_count = count($contact_diff['insertions']);
        $deletion_count = count($contact_diff['deletions']);
        $this->log("Synchronising tag '{$tag_name}': {$insertion_count} additions, {$deletion_count} removals.");

        // step 2: remove the ones that are not in the new list
        if (!empty($contact_diff['deletions'])) {
            $this->log("Removing tag '{$tag_name}' [{$tag_id}] from the following contact IDs: " . implode(',', $contact_diff['deletions']));
            \Civi\Api4\EntityTag::delete(false)
                    ->addWhere('tag_id', '=', $tag_id)
                    ->addWhere('entity_table', '=', 'civicrm_contact')
                    ->addWhere('entity_id', 'IN', $contact_diff['deletions'])
                    ->execute();
        }

        // step 3: add the ones that are not in the current list
        if (!empty($contact_diff['insertions'])) {
            $this->log("Adding tag '{$tag_name}' [{$tag_id}] to the following contact IDs: " . implode(',', $contact_diff['insertions']));
            foreach ($contact_diff['insertions'] as $contact_id) {
                \Civi\Api4\EntityTag::create(false)
                        ->addValue('entity_table', 'civicrm_contact')
                        ->addValue('tag_id', $tag_id)
                        ->addValue('entity_id', $contact_id)
                        ->execute();
            }
        }

        return [
            'contacts_added'   => $contact_diff['insertions'],
            'contacts_removed' => $contact_diff['deletions'],
        ];
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
        $tag_search = \Civi\Api4\Tag::get(false)
                ->addSelect('id')
                ->addWhere('name', '=', $tag_name)
                ->setLimit(1)
                ->execute()
                ->first();

        if (empty($tag_search['id'])) {
            // tag does not exist yet => create!
            $new_tag = civicrm_api4('Tag', 'create', [
                    'values' => [
                            'name' => $tag_name,
                            'label' => $tag_label ?? $tag_name,
                            'description' => $tag_description ?? '',
                            'used_for' => [$used_for],
                    ],
                    'checkPermissions' => false,
            ]);
            $tag_id = $new_tag->first()['id'];
            $this->log("Created new tag '{$tag_name}' with label '{$tag_label}', ID is [{$tag_id}].");
            return $tag_id;
        } else {
            return $tag_search['id'];
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


    /**
     * Compare two arrays and return a list of items only in array1 (deletions) and only in array2 (insertions)
     *
     * @param array $array1 The 'original' array, for comparison. Items that exist here only are considered to be deleted (deletions).
     * @param array $array2 The 'new' array. Items that exist here only are considered to be new items (insertions).
     * @param ?array $keysToCompare A list of array key names that should be used for comparison of arrays (ignore all other keys)
     * @return array[] array with keys 'insertions' and 'deletions'
     *
     * @note copied from https://gist.github.com/cjthompson/5485005
     */
    public static function arrayDifference(array $array1, array $array2, array $keysToCompare = null) : array {
        $serialize = function (&$item, $idx, $keysToCompare) {
            if (is_array($item) && $keysToCompare) {
                $a = array();
                foreach ($keysToCompare as $k) {
                    if (array_key_exists($k, $item)) {
                        $a[$k] = $item[$k];
                    }
                }
                $item = $a;
            }
            $item = serialize($item);
        };

        $deserialize = function (&$item) {
            $item = unserialize($item);
        };

        array_walk($array1, $serialize, $keysToCompare);
        array_walk($array2, $serialize, $keysToCompare);

        // Items that are in the original array but not the new one
        $deletions = array_diff($array1, $array2);
        $insertions = array_diff($array2, $array1);

        array_walk($insertions, $deserialize);
        array_walk($deletions, $deserialize);

        return ['insertions' => $insertions, 'deletions' => $deletions];
    }
}
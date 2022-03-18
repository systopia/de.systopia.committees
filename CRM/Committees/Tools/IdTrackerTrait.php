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

use CRM_Committees_ExtensionUtil as E;

/**
 * This trait adds features based on
 *  the "Identity Tracker" extension
 *
 * @see https://github.com/systopia/de.systopia.identitytracker
 */
trait CRM_Committees_Tools_IdTrackerTrait
{
    /** @var array static ID cache */
    protected static $idt_trackerID2contactID = null;

    /** @var array static reverse ID cache */
    protected static $contactID2idt_trackerID = null;

    /**
     * Get the (cached) contact ID via the IdentityTracker
     *
     * @param string $internal_id
     *   ID as used by the data source
     *
     * @param string $id_type
     *   a registered contact tracker type
     *
     * @param string $prefix
     *   ID prefix
     */
    public function getIDTContactID($internal_id, $id_type, $prefix = '')
    {
        // load all tracker IDs via SQL (once)
        if (self::$idt_trackerID2contactID === null) {
            self::$idt_trackerID2contactID = [];
            $id_record = CRM_Core_DAO::executeQuery(
                "
                SELECT
                  entity_id   AS contact_id,
                  identifier  AS tracker_id
                FROM civicrm_value_contact_id_history
                WHERE identifier_type = %1
                ",
                [1 => [$id_type, 'String']]
            );
            while ($id_record->fetch()) {
                self::$idt_trackerID2contactID[$id_record->tracker_id] = $id_record->contact_id;
            }
        }

        // look up tracker
        $tracker_id = $prefix . $internal_id;
        return self::$idt_trackerID2contactID[$tracker_id] ?? null;
    }

    /**
     * Get the (cached) contact ID via the IdentityTracker
     *
     * @param string $internal_id
     *   ID as used by the data source
     *
     * @param string $id_type
     *   a registered contact tracker type
     *
     * @param string $prefix
     *   ID prefix
     */
    public function getContactIDtoTids($id_type, $prefix = '')
    {
        // make sure the cache is filled
        if (self::$idt_trackerID2contactID === null) {
            $this->getIDTContactID(0, $id_type, $prefix);
        }

        // compile a reverse list
        $contactID_2_trackerIDs = [];
        foreach (self::$idt_trackerID2contactID as $tracker_id => $contact_id) {
            $contactID_2_trackerIDs[$contact_id][] = $tracker_id;
        }

        return $contactID_2_trackerIDs;
    }


    /**
     * Write a new ID Tracker ID via the IdentityTracker
     *
     * @param string $internal_id
     *   ID as used by the data source
     *
     * @param string $civicrm_id
     *   ID as used by the data source
     *
     * @param string $id_type
     *   a registered contact tracker type
     *
     * @param string $prefix
     *   ID prefix
     */
    public function setIDTContactID($internal_id, $civicrm_id, $id_type, $prefix = '')
    {
        // make sure the cache is filled
        if (self::$idt_trackerID2contactID === null) {
            $this->getIDTContactID($internal_id, $id_type, $prefix);
        }

        // write to DB
        $tracker_id = $prefix . $internal_id;
        civicrm_api3('Contact', 'addidentity', [
            'contact_id' => $civicrm_id,
            'identifier_type' => $id_type,
            'identifier' => $tracker_id
        ]);

        // add to our cache
        self::$idt_trackerID2contactID[$tracker_id] = $civicrm_id;
    }


    /**
     * Check the ID Tracker requirements
     *
     * @param CRM_Committees_Plugin_Base $plugin
     *   the plugin
     */
    public function checkIdTrackerRequirements(CRM_Committees_Plugin_Base $plugin)
    {
        if (!$plugin->extensionAvailable('de.systopia.identitytracker')) {
            $plugin->registerMissingRequirement(
                'de.systopia.identitytracker',
                E::ts("ID Tracker extension missing"),
                E::ts("Please install the <code>de.systopia.identitytracker</code> extension from <a href='https://github.com/systopia/de.systopia.identitytracker'>here</a>.")
            );
        }
    }

    /**
     * If the ID tracker (de.systopia.identitytracker) is used, this can be
     *  used to make sure a specific tracker type is present
     */
    public function registerIDTrackerType($key, $label, $description = 'de.systopia.committee')
    {
        // also: add the 'Remote Contact' type to the identity tracker
        $exists_count = civicrm_api3(
            'OptionValue',
            'getcount',
            [
                'option_group_id' => 'contact_id_history_type',
                'value' => $key,
            ]
        );
        switch ($exists_count) {
            case 0:
                // not there -> create
                civicrm_api3(
                    'OptionValue',
                    'create',
                    [
                        'option_group_id' => 'contact_id_history_type',
                        'value' => $key,
                        'is_reserved' => 1,
                        'description' => $description,
                        'name' => $key,
                        'label' => $label,
                    ]
                );
                break;

            case 1:
                // does exist, nothing to do here
                break;

            default:
                // more than one exists: that's not good!
                throw new Exception("There are already multiple identity tracker types '$key'.");
                break;
        }
    }

}
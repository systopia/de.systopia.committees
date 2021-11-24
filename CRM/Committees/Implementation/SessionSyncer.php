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
 * Syncer for Session XLS Export
 *
 * @todo migrate to separate extension
 */
class CRM_Committees_Implementation_SessionSyncer extends CRM_Committees_Plugin_Syncer
{
    /**
     * This function will be called *before* the plugin will do it's work.
     *
     * If your implementation has any external dependencies, you should
     *  register those with the registerMissingRequirement function.
     *
     */
    public function checkRequirements()
    {
        // we need the identity tracker
        if (!$this->extensionAvailable('de.systopia.identitytracker')) {
            $this->registerMissingRequirement('de.systopia.identitytracker',
                E::ts("ID Tracker extension missing"),
                E::ts("Please install the <code>de.systopia.identitytracker</code> extension from <a href='https://github.com/systopia/de.systopia.identitytracker'>here</a>.")
            );
        }

        // we need the identity tracker
        if (!$this->extensionAvailable('de.systopia.xcm')) {
            $this->registerMissingRequirement('de.systopia.xcm',
                                              E::ts("Extended Contact Machter (XCM) extension missing"),
                                              E::ts("Please install the <code>de.systopia.xcm</code> extension from <a href='https://github.com/systopia/de.systopia.xcm'>here</a>.")
            );
        }
    }



    /**
     * Sync the given model into the CiviCRM
     *
     * @param CRM_Committees_Model_Model $model
     *   the model to be synced to this CiviCRM
     *
     * @param boolean $transaction
     *   should the sync happen in a single transaction
     *
     * @return boolean
     */
    public function syncModel($model, $transaction = true)
    {

        // first, make sure some stuff is there
        $this->registerIDTrackerType();
        $this->registerCommitteeContactType();
        $this->registerRelationshipType();

        // todo: load the current model

        if ($transaction) {
            $transaction = new CRM_Core_Transaction();
        }

        // todo: diff models sync instead of import
        // todo: instead, we'll do a simple import for now
        $this->simpleImport($model);

        if ($transaction) {
            $transaction->commit();
        }
    }

    public function getLabel(): string
    {
        return E::ts("Session Syncer");
    }

    public function getDescription(): string
    {
        return E::ts("Imports Session Data");
    }
}
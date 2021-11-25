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
    use CRM_Committees_Tools_IdTrackerTrait;
    use CRM_Committees_Tools_XcmTrait;

    const CONTACT_TRACKER_TYPE = 'session';
    const CONTACT_TRACKER_PREFIX = 'SESSION-';
    const XCM_PERSON_PROFILE = 'session_person';
    const XCM_COMMITTEE_PROFILE = 'session_organisation';

    public function getLabel(): string
    {
        return E::ts("Session Syncer");
    }

    public function getDescription(): string
    {
        return E::ts("Imports Session Data");
    }

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
        $this->checkIdTrackerRequirements($this);

        // we need the extended contact matcher (XCM)
        $this->checkXCMRequirements($this, [self::XCM_PERSON_PROFILE, self::XCM_COMMITTEE_PROFILE]);
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
    public function syncModel($model, $transaction = false)
    {
        // first, make sure some stuff is there
        $this->registerIDTrackerType(self::CONTACT_TRACKER_TYPE, "Session ID");
        $this->createContactTypeIfNotExists('Gremium', "Gremium (Session)", 'Organization');
        $this->createRelationshipTypeIfNotExists(
            'is_committee_member_of',
            'committee_has_member',
            "Gremienmitglied bei",
            'Gremienmitglied',
            'Individual',
            'Organization',
            null,
            'Gremium',
            "Aus der Sessions DB"
        );

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

    /**
     * Quick hack: simply import all the entities, NO SYNCING!
     *
     * @todo replace with real synchronisation
     *
     * @param CRM_Committees_Model_Model $model
     */
    protected function simpleImport($model)
    {
        // import Gremien
        foreach ($model->getAllCommittees() as $committee) {
            /** @var CRM_Committees_Model_Committee $committee */
            $data = $committee->getData();
            $data['contact_type'] = 'Organization';
            $data['contact_sub_type'] = 'Gremium';
            $data['organization_name'] = $data['name'];
            $gremium_id = $this->runXCM($data, 'session_organisation');
            $this->setIDTContactID($committee->getID(), $gremium_id, self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
        }

        // import contacts
        foreach ($model->getAllPersons() as $person) {
            /** @var CRM_Committees_Model_Person $person */
            $data = $person->getData();
            $data['contact_type'] = 'Individual';
            // todo: join phones, emails, addresses

            $data['id'] = $this->getIDTContactID($person->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
            $person_id = $this->runXCM($data, 'session_person');
            $this->setIDTContactID($person->getID(), $person_id, self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
        }

        // import memberships
        foreach ($model->getAllMemberships() as $membership) {
            /** @var CRM_Committees_Model_Membership $membership */
            $committee_id = $this->getIDTContactID($membership->getCommittee()->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
            $person_id = $this->getIDTContactID($membership->getPerson()->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
            if (empty($committee_id) || empty($person_id)) {
                $this->logError("Person or Gremium wasn't identified or created.");
            } else {
                try {
                    civicrm_api3('Relationship', 'create', [
                        'contact_id_a' => $person_id,
                        'contact_id_b' => $committee_id,
                        'relationship_type_id' => $this->getRelationshipTypeID('is_committee_member_of'),
                        'start_date' => $membership->getAttribute('start_date'),
                        'end_date' => $membership->getAttribute('end_date'),
                        'description' => $membership->getAttribute('title'),
                    ]);
                } catch (CiviCRM_API3_Exception $exception) {
                    $this->logException($exception, "Person or Gremium wasn't identified or created.");
                }
            }
        }
    }
}
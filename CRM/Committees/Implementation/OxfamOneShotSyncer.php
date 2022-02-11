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
class CRM_Committees_Implementation_OxfamOneShotSyncer extends CRM_Committees_Plugin_Syncer
{
    use CRM_Committees_Tools_IdTrackerTrait;

    /** @var string committee.type value for parliamentary committee (Ausschuss) */
    const COMMITTEE_TYPE_PARLIAMENTARY_COMMITTEE = 'parliamentary_committee';

    /** @var string committee.type value for parliamentary group (Fraktion) */
    const COMMITTEE_TYPE_PARLIAMENTARY_GROUP = 'parliamentary_group';

    const ID_TRACKER_TYPE = 'kuerschners';
    const ID_TRACKER_PREFIX = 'KUE-';
    const CONTACT_SOURCE = 'kuerschners_MdB_2021';

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
        // first, make sure some stuff is there:
        // 1. ID Tracker
        $this->registerIDTrackerType(self::ID_TRACKER_TYPE, "Kürschners");

        // 2. Contact group 'Lobby-Kontakte'
        $lobby_contact_group_id = $this->getOrCreateContactGroup(['title' => E::ts('Lobby-Kontakte')]);

        // 3. add relationship types
        // todo

        /**************************************
         **        RUN SYNCHRONISATION       **
         **************************************/

        // sync committees
        $committee_id_to_contact_id = [];
        $committees = $model->getAllCommittees();
        $this->log("Syncing " . count($committees) . " committees...");
        foreach ($model->getAllCommittees() as $committee) {
            $committee_name = $committee['name'];
            if ($committee['type'] == self::COMMITTEE_TYPE_PARLIAMENTARY_GROUP) {
                $committee_name = E::ts("Fraktion %1 im Deutschen Bundestag", [1 => $committee['name']]);
            }
            // find contact
            $search_result = $this->callApi3('Contact', 'get', [
                'organization_name' => $committee_name,
                'contact_type' => 'Organization'
            ]);
            if (!empty($search_result['id'])) {
                // single hit
                $this->log("Committee '{$committee_name}' found: " . $search_result['id']);
                $committee_id_to_contact_id[$committee['id']] = $search_result['id'];

            } else if (count($search_result['values']) > 1) {
                // multiple hits
                $first_committee = reset($search_result['values']);
                $committee_id_to_contact_id[$committee['id']] = $first_committee['id'];
                $this->log("Committee '{$committee_name}' not unique! Using ID [{$first_committee['id']}]", 'warn');

            } else {
                // doesn't exist -> create
                $create_result = $this->callApi3('Contact', 'create', [
                    'organization_name' => $committee_name,
                    'contact_type' => 'Organization'
                ]);
                $committee_id_to_contact_id[$committee['id']] = $create_result['id'];
                $this->log("Committee '{$committee_name}' created: ID [{$create_result['id']}");
            }
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
        // join addresses, emails, phones
        $model->joinAddressesToPersons();
        $model->joinEmailsToPersons();
        $model->joinPhonesToPersons();

        // import Gremien
        foreach ($model->getAllCommittees() as $committee) {
            /** @var CRM_Committees_Model_Committee $committee */
            $data = $committee->getData();
            $data['contact_type'] = 'Organization';
            $data['contact_sub_type'] = 'Gremium';
            $data['organization_name'] = $data['name'];
            $gremium_id = $this->runXCM($data, 'session_organisation');
            $this->log("Gremium '{$data['name']}' ([{$gremium_id}]) imported/updated.");
            $this->setIDTContactID($committee->getID(), $gremium_id, self::CONTACT_TRACKER_TYPE, self::COMMITTEE_TRACKER_PREFIX);
        }
        $this->log(count($model->getAllCommittees()) . " committees imported/updated.");

        // import contacts
        $counter = 0; // used to restrict log spamming
        $person_count = count($model->getAllPersons());
        $this->log("Starting to import/update {$person_count} persons.");
        foreach ($model->getAllPersons() as $person) {
            /** @var CRM_Committees_Model_Person $person */
            $data = $person->getData();
            $data['contact_type'] = 'Individual';
            $data['id'] = $this->getIDTContactID($person->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
            $person_id = $this->runXCM($data, 'session_person', false);
            $this->setIDTContactID($person->getID(), $person_id, self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
            $counter++;
            if ($counter % 25 == 0) {
                $this->log("{$counter} persons imported/updated.");
            }
        }
        $this->log("{$counter} persons imported/updated.");

        // import memberships
        foreach ($model->getAllMemberships() as $membership) {
            /** @var CRM_Committees_Model_Membership $membership */
            // get person
            $person = $membership->getPerson();
            if (empty($person)) {
                $mid = $membership->getID();
                $this->log("Membership [{$mid}] has no person, this should not happen.");
                continue;
            }

            // get committee
            $gremium = $membership->getCommittee();
            if (empty($gremium)) {
                $mid = $membership->getID();
                $this->log("Membership [{$mid}] has no committee, this should not happen.");
                continue;
            }

            // find person
            $person_id = $this->getIDTContactID($person->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
            if (empty($person_id)) {
                $external_id = $person->getID();
                $this->logError("Committees person [{$external_id}] wasn't identified or created.");
                continue;
            }

            // find gremium
            $gremium_id = $this->getIDTContactID($gremium->getID(), self::CONTACT_TRACKER_TYPE, self::COMMITTEE_TRACKER_PREFIX);
            if (empty($gremium_id)) {
                $external_id = $gremium->getID();
                $this->logError("Committee [{$external_id}] wasn't identified or created.");
                continue;
            }

            // create relationship
            try {
                $relationship_params =  [
                    'contact_id_a' => $person_id,
                    'contact_id_b' => $gremium_id,
                    'relationship_type_id' => $this->getRelationshipTypeID('is_committee_member_of'),
                    'start_date' => $membership->getAttribute('start_date'),
                    'end_date' => $membership->getAttribute('end_date'),
                    'description' => $membership->getAttribute('title'),
                ];
                civicrm_api3('Relationship', 'create', $relationship_params);
            } catch (CiviCRM_API3_Exception $exception) {
                $this->logException($exception, "Relationship between person [{$person->getID()}] and committee [{$gremium->getID()}] could not be created. Error was " . $exception->getMessage());
            }
        }

        // wrap it up
        $this->log("Committee import complete.");
        $this->log("WARNING: a full synchronisation, i.e. the retirement of ended relationships is not yet implemented. This would mainly work for the initial import.");
    }
}
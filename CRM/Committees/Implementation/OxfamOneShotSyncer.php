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
        // first, make sure some stuff is there
        $this->registerIDTrackerType(self::ID_TRACKER_TYPE, "Kürschners");
        $location_type_id = $this->createLocationTypeIfNotExists('name', "Gremium (Session)", 'Organization');
        $lobby_contact_group_id = $this->getOrCreateGroup('Lobby-Kontakte');

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

        // todo: diff models sync instead of import
        // todo: instead, we'll do a simple import for now
        $this->simpleImport($model);
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
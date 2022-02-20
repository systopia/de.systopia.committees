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
 * @todo migrate to separate extension or leave as example?
 */
class CRM_Committees_Implementation_PersonalOfficeSyncer extends CRM_Committees_Plugin_Syncer
{
    use CRM_Committees_Tools_IdTrackerTrait;
    use CRM_Committees_Tools_XcmTrait;

    const CONTACT_TRACKER_TYPE = 'personal_office';
    const CONTACT_TRACKER_PREFIX = 'PO-';
    const CONTACT_CONTACT_TYPE_NAME = 'Pfarrer_in';
    const CONTACT_CONTACT_TYPE_LABEL = 'Pfarrer*in';
    const XCM_PERSON_PROFILE = 'personal_office';

    /** @var string custom field id (group_name.field_name) for the EKIR hierarchical identifier */
    const ORGANISATION_EKIR_ID_FIELD = 'gmv_data.gmv_data_identifier';

    /** @var string  custom field id (group_name.field_name) for the job title custom field */
    const CONTACT_JOB_TITLE_KEY_FIELD = 'pfarrer_innen.pfarrer_innen_job_title_key';

    /** @var array mapping for the job_key fields */
    static $CONTACT_JOB_TITLE_KEY_MAPPING = [
        'KK-Ebene:Angest.Pfarrer'      => 1,
        'KK-Ebene:Pfarrer'             => 2,
        'LK-Ebene:Angest.Pfarrer'      => 3,
        'LK-Ebene:Pfarrer'             => 4,
        'TA-Probedienst:Angestellte'   => 5,
        'TA-Probedienst:Beamte'        => 6,
        'TA-Probezeit: Angestellte'    => 7,
        'TA-Vikare:Angestellte'        => 8,
        'TA-Vikare:Beamte'             => 9,
        'Theolog.Ausbild:Angestellte'  => 10,
    ];

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
        $this->checkXCMRequirements($this, [self::XCM_PERSON_PROFILE]);

        // check if a certain custom field exists
        if (!$this->customFieldExists(self::ORGANISATION_EKIR_ID_FIELD)) {
            $this->registerMissingRequirement(
                self::ORGANISATION_EKIR_ID_FIELD,
                E::ts("EKIR Organisation ID field not found."),
                E::ts("Please add the '%1' custom field or update the configuration/requirements.", [
                    1 => self::ORGANISATION_EKIR_ID_FIELD
                ])
            );
            return false;
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
    public function syncModel($model, $transaction = false)
    {
        // first, make sure some stuff is there
        $this->registerIDTrackerType(self::CONTACT_TRACKER_TYPE, "Personal Office ID");

        // make sure Pfarrer*in contact sub type exists
        $this->createContactTypeIfNotExists(self::CONTACT_CONTACT_TYPE_NAME, self::CONTACT_CONTACT_TYPE_LABEL, 'Individual');

        $customData = new CRM_Gmv_CustomData(E::LONG_NAME);
        $customData->syncOptionGroup(E::path('resources/PersonalOffice/option_group_pfarrer_innen.json'));
        $customData->syncCustomGroup(E::path('resources/PersonalOffice/custom_group_pfarrer_innen.json'));

        if ($transaction) {
            $transaction = new CRM_Core_Transaction();
        }

        // todo: diff models sync instead of import
        // todo: instead, we'll do a simple import for now
        $this->logError("Simple Import Only!", 'warning', "This module currently only does a simple import and needs to be updated to fully synchronise.");
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
        // join addresses, emails, phones
        $model->joinAddressesToPersons();
        $model->joinEmailsToPersons();

        // import contacts
        $counter = 0; // used to restrict log spamming
        foreach ($model->getAllPersons() as $person) {
            /** @var CRM_Committees_Model_Person $person */
            $data = $person->getData();
            $data['contact_type'] = 'Individual';
            $data['contact_sub_type'] = self::CONTACT_CONTACT_TYPE_NAME;

            // map job_title_key field
            if (isset($data['job_title_key']) && isset(self::$CONTACT_JOB_TITLE_KEY_MAPPING[$data['job_title_key']])) {
                $data[self::CONTACT_JOB_TITLE_KEY_FIELD] = self::$CONTACT_JOB_TITLE_KEY_MAPPING[$data['job_title_key']];
            }
            unset($data['job_title_key']);

            // see if the contact's already there
            CRM_Committees_CustomData::resolveCustomFields($data);
            $data['id'] = $this->getIDTContactID($person->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);

            // and finally run the XCM
            $person_id = $this->runXCM($data, self::XCM_PERSON_PROFILE, false);
            $this->setIDTContactID($person->getID(), $person_id, self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
            $counter++;
            if ($counter % 25 == 0) {
                $this->log("{$counter} persons imported/updated.");
            }
        }

        // import employment
        $all_employments = $model->getAllMemberships();
        $employment_count = count($all_employments);
        $employments_imported = 0;
        $this->log("Importing {$employment_count} employments...");
        foreach ($all_employments as $employment) {
            // get person
            $person = $employment->getPerson();
            if (empty($person)) {
                $mid = $employment->getID();
                $this->log("Employment [{$mid}] has no employee, this should not happen.");
                continue;
            }

            // find person
            $employee_id = $this->getIDTContactID($person->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
            if (empty($employee_id)) {
                $external_id = $person->getID();
                $this->logError("Employee [{$external_id}] wasn't identified or created.");
                continue;
            }

            // get employer
            $employer = $employment->getCommittee();
            if (empty($employer)) {
                $mid = $employment->getID();
                $this->log("Employment [{$mid}] has no employer, this should not happen.");
                continue;
            }

            // find employer contact
            $employer_ekir_id = $employer->getID();
            $employer_contact = null;
            try {
                $ekir_field_query = [
                    self::ORGANISATION_EKIR_ID_FIELD => $employer_ekir_id,
                    'return' => 'id'
                ];
                CRM_Committees_CustomData::resolveCustomFields($ekir_field_query);
                $employer_contact = civicrm_api3('Contact', 'getsingle', $ekir_field_query);
            } catch (CiviCRM_API3_Exception $ex) {
                $this->log("EKIR entity [{$employer_ekir_id}] is listed as employer, but wasn't not found in the system.");
                continue;
            }

            try {
                civicrm_api3('Relationship', 'create', [
                    'contact_id_a' => $employee_id,
                    'contact_id_b' => $employer_contact['id'],
                    'relationship_type_id' => $this->getRelationshipTypeID('Employee of'),
                    //                    'start_date' => $employment->getAttribute('start_date'),
                    //                    'end_date' => $employment->getAttribute('end_date'),
                    //                    'description' => $employment->getAttribute('title'),
                ]);
                $employments_imported++;
            } catch (CiviCRM_API3_Exception $ex) {
                $this->log("Couldn't create EKIR employment of [{$employee_id}] with [{$employer_contact['id']}]: " . $ex->getMessage());
                continue;
            }
        }
        $this->log("{$employments_imported} of {$employment_count} employments imported.");
        $this->log("Simple import complete.");
    }
}
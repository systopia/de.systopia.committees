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
 * Syncer for PersonalOffice (PO) XLS Export
 *
 * @todo migrate to separate extension or leave as example?
 */
class CRM_Committees_Implementation_PersonalOfficeSyncer extends CRM_Committees_Plugin_Syncer
{
    use CRM_Committees_Tools_IdTrackerTrait;
    use CRM_Committees_Tools_XcmTrait;
    use CRM_Committees_Tools_ModelExtractionTrait;

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
        //$this->checkXCMRequirements($this, [self::XCM_PERSON_PROFILE]);

        // check if the employer relationship is there
        try {
            $this->getRelationshipTypeID('Employee of');
        } catch (Exception $ex) {
            $this->registerMissingRequirement(
                    'employee_relationship',
                    "'Employee of' relationship doesn't exist or is not active.",
                    "Please make sure the relationship 'Employee of' is available"
            );
        }


        // make sure a certain custom field exists
        $db_schema_changed = false;
        if (!$this->customFieldExists(CRM_Committees_Implementation_PersonalOfficeSyncer::ORGANISATION_EKIR_ID_FIELD)) {
            // check if the group is missing:
            [$group_name, $field_name] = explode('.', CRM_Committees_Implementation_PersonalOfficeSyncer::ORGANISATION_EKIR_ID_FIELD);
            $custom_groups = (array) CRM_Committees_CustomData::getGroup2Name();
            if (!in_array($group_name, $custom_groups)) {
                // create group
                civicrm_api3('CustomGroup', 'create', [
                    "name" => "gmv_data",
                    "title" => "EKIR Strukturdaten",
                    "extends" => "Organization",
                    //"extends_entity_column_value" => ["Kirchenkreis", "Kirchengemeinde", "Pfarrstelle"],
                    "style" => "Tab",
                    "weight" => "12",
                    "is_active" => "1",
                    "table_name" => "civicrm_value_gmv_data",
                    "is_multiple" => "0",
                    "collapse_adv_display" => "0",
                    "is_reserved" => "0",
                    "is_public" => "1",
                    "icon" => "fa-info"
                ]);
                $this->log("Custom Group {$group_name} created.");
                CRM_Committees_CustomData::flushCashes();
                $db_schema_changed = true;
            }

            // check if the field is missing:
            $custom_field = CRM_Committees_CustomData::getCustomField($group_name, $field_name);
            if (empty($custom_field)) {
                // create field
                civicrm_api3('CustomField', 'create', [
                    "custom_group_id" => "gmv_data",
                    "name" => "gmv_data_identifier",
                    "label" => "EKIR ID",
                    "data_type" => "String",
                    "html_type" => "Text",
                    "is_required" => "0",
                    "is_searchable" => "1",
                    "is_search_range" => "1",
                    "is_active" => "1",
                    "column_name" => "identifier",
                    "serialize" => "0",
                ]);
            }
            $this->log("Custom Field {$field_name} created.");
            CRM_Committees_CustomData::flushCashes();
            $db_schema_changed = true;
        }

//        if ($db_schema_changed) {
//            throw new Exception("New custom fields created, please run the import process again.");
//        }
        return parent::checkRequirements();
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
        $this->registerIDTrackerType(self::CONTACT_TRACKER_TYPE, "Personal Office ID");

        // make sure Pfarrer*in contact sub type exists
        $this->createContactTypeIfNotExists(self::CONTACT_CONTACT_TYPE_NAME, self::CONTACT_CONTACT_TYPE_LABEL, 'Individual');

//        // todo: enable for local testing
//        $this->log("WARNING! CustomData synchronisation still active!", 'warning');
//        $customData = new CRM_Committees_CustomData(E::LONG_NAME);
//        $customData->syncOptionGroup(E::path('resources/PersonalOffice/option_group_pfarrer_innen.json'));
//        $customData->syncCustomGroup(E::path('resources/PersonalOffice/custom_group_pfarrer_innen.json'));
//        $customData->syncCustomGroup(E::path('resources/PersonalOffice/custom_group_gmv_data.json'));
//        CRM_Committees_CustomData::flushCashes();

        if (!$this->customFieldExists(CRM_Committees_Implementation_PersonalOfficeSyncer::ORGANISATION_EKIR_ID_FIELD)) {
            $field_key = CRM_Committees_Implementation_PersonalOfficeSyncer::ORGANISATION_EKIR_ID_FIELD;
            $this->logError("'EKIR ID' ({$field_key}) field missing!", 'This field is needed for synchronisation');
        }

        if (!$this->customFieldExists(self::CONTACT_JOB_TITLE_KEY_FIELD)) {
            $field_key = self::CONTACT_JOB_TITLE_KEY_FIELD;
            $this->logError("'Job Title' ({$field_key}) field missing!", 'This field is needed for synchronisation');
        }

        if ($transaction) {
            $transaction = new CRM_Core_Transaction();
        }


        /**************************************
         **        RUN SYNCHRONISATION       **
         **************************************/

        /** $present_model CRM_Committees_Model_Model this model will contain the data currently present in the DB  */
        $present_model = new CRM_Committees_Model_Model();
        $present_model->setProperty(CRM_Committees_Model_Email::MODEL_PROPERTY_EMAIL_LOWER_CASE, true);

        /**********************************************
         **               SYNC COMMITTEES            **
         **             (read: 'divisions')          **
         **********************************************/

        // now extract current committees and run the diff
        $this->extractCurrentCommittees($model, $present_model);
        [$new_divisions, $changed_divisions, $obsolete_divisions] = $present_model->diffCommittees($model, ['contact_id', 'id', 'name']);
        $total_division_count = count($present_model->getAllCommittees());
        $new_division_count = count($new_divisions);
        $obsolete_division_count = count($obsolete_divisions);
        $this->log("Identified {$total_division_count} existing divisions in the system, {$obsolete_division_count} of which might be obsolete.");
        $this->log("{$new_division_count} new divisions will be created.");
        foreach ($new_divisions as $new_division) {
            // we want to create new divisions, otherwise we can't continue
            /** @var CRM_Committees_Model_Committee $new_division */
            try {
                $new_division_data = [
                    'organization_name' => $new_division->getAttribute('name'),
                    'contact_type' => 'Organization',
                    'contact_sub_type' => $this->getContactSubTypeFromId($new_division->getID()),
                    CRM_Committees_Implementation_PersonalOfficeSyncer::ORGANISATION_EKIR_ID_FIELD => $new_division->getID(),
                ];
                CRM_Committees_CustomData::labelCustomFields($new_division_data);
                $contact_type = $new_division_data['contact_sub_type'] ?? 'Organization';
                $result = civicrm_api3('Contact', 'create', $new_division_data);
                $new_division->setAttribute('contact_id', $result['id']);
                $new_division->setAttribute('is_new', true);
                $this->log("Created new division [#{$result['id']}]: '{$new_division->getAttribute('name')}'");
            } catch (Exception $ex) {
                $this->log("Couldn't create division [{$new_division->getID()}]: '{$new_division->getAttribute('name')}' - error was: '{$ex->getMessage()}'", 'warning');
            }
        }

        // report changed committees
        if ($changed_divisions) {
            $changed_division_count = count($changed_divisions);
            $this->log("Will NOT update names of {$changed_division_count} divisions!", 'info');
        }

        if ($obsolete_divisions) {
            $obsolete_division_count = count($obsolete_divisions);
            $this->log("There are {$obsolete_division_count} unreferenced divisions, but they will NOT be removed.");
        }

        /**********************************************
         **           SYNC BASE CONTACTS            **
         **********************************************/
        $this->log("Syncing " . count($model->getAllPersons()) . " data sets...");

        // join addresses, emails
        $model->joinAddressesToPersons();
        $model->joinEmailsToPersons();

        // then compare to current model and apply changes
        $this->extractCurrentContacts($model, $present_model, self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
        [$new_persons, $changed_persons, $obsolete_persons] = $present_model->diffPersons($model, ['contact_id', 'formal_title', 'prefix', 'street_address', 'house_number', 'postal_code', 'city', 'email', 'supplemental_address_1', 'gender_id', 'prefix_id', 'suffix_id', 'job_title_key', 'country_id']);

        foreach ($new_persons as $new_person) {
            /** @var CRM_Committees_Model_Person $new_person */
            $person_data                 = $new_person->getDataWithout(['id']);
            $person_data['contact_type'] = 'Individual';
            $person_data['source']       = 'PO Import ' . date('Y-m-d');
            $person_data['tag_id']       = 'Personal Office';

            // convert job title
            $job_title = $person_data['job_title_key'] ?? null;
            $person_data[self::CONTACT_JOB_TITLE_KEY_FIELD] =
                    $this->getOrCreateOptionValue(['label' => $job_title], 'pfarrer_innen_job_title_key')['value'] ?? '';
            try {
                CRM_Committees_CustomData::resolveCustomFields($person_data);
                $result = $this->callApi3('Contact', 'create', $person_data);
                $this->setIDTContactID(
                        $new_person->getID(),
                        $result['id'],
                        self::CONTACT_TRACKER_TYPE,
                        self::CONTACT_TRACKER_PREFIX
                );
                $new_person->setAttribute('contact_id', $result['id']);
                $new_person->setAttribute('is_new', true);

                // add to the present model
                $present_model->addPerson($new_person->getData());
                $this->log("PO Contact [{$new_person->getID()}] created with CiviCRM-ID [#{$result['id']}].");
            } catch (Exception $exception) {
                $this->logError("Exception when trying to create new contact [{$new_person->getID()}]: " . $exception->getMessage());
            }
        }

        if (!$new_persons) {
            $this->log("No new contacts detected in import data.");
        }


        // apply changes to existing contacts
        foreach ($changed_persons as $changed_person) {
            /** @var CRM_Committees_Model_Person $changed_person */
            $contact_id = $changed_person->getAttribute('contact_id');
            $differing_attributes = explode(',', $changed_person->getAttribute('differing_attributes'));
            $differing_values = $changed_person->getAttribute('differing_values');
            foreach ($differing_attributes as $differing_attribute) {
                $this->log("TODO: Change attribute '{$differing_attribute}' of person with CiviCRM-ID [#{$contact_id}] from '{$differing_values[$differing_attribute][0]}' to '{$differing_values[$differing_attribute][1]}'?");
            }
        }

        // note obsolete contacts
        if (!empty($obsolete_persons)) {
            $obsolete_person_count = count($obsolete_persons);
            $this->log("There are {$obsolete_person_count} potentially relevant persons in CiviCRM that are not listed in the new data set. Those will *not* be deleted:");
//            foreach ($obsolete_persons as $obsolete_person) {
//                /** @var CRM_Committees_Model_Person $obsolete_person */
//                $contact_id = $this->getIDTContactID($obsolete_person->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
//                if ($contact_id) {
//                    $contact = civicrm_api3('Contact', 'getsingle', ['id' => $contact_id, 'return' => 'id,display_name']);
//                    $this->log("Not deleting obsolete contact [#{$contact['id']}]: " . $this->obfuscate($contact['display_name']));
//                } else {
//                    $this->log("Couldn't find person [{$obsolete_person->getID()}], so not deleting.");
//                }
//            }
        }

        /**********************************************
         **           SYNC CONTACT EMAILS            **
         **********************************************/
        $this->extractCurrentDetails($model, $present_model, 'email', ['strtolower']);
        [$new_emails, $changed_emails, $obsolete_emails] = $present_model->diffEmails($model, ['location_type', 'id']);
        foreach ($new_emails as $email) {
            /** @var CRM_Committees_Model_Email $email */
            $email_data = $email->getData();
            $email_data['location_type_id'] = 'Work';
            $email_data['is_primary'] = 1;
            $person = $email->getContact($present_model);
            if (!$person->getAttribute('is_new')) {
                // new contact: create
                /** @var CRM_Committees_Model_Email $email */
                $email_data = $email->getData();
                $email_data['location_type_id'] = 'Work';
                $email_data['is_primary'] = 1;
                $email_data['contact_id'] = $person->getAttribute('contact_id');
                $this->callApi3('Email', 'create', $email_data);
                $this->log("Added email '{$email_data['email']}' to new contact [#{$email_data['contact_id']}]?");
            } else {
                $email_data['contact_id'] = $person->getAttribute('contact_id');
                $this->log("TODO: add email '{$email_data['email']}' to existing contact [#{$email_data['contact_id']}]?");
            }
        }
        if (!$new_emails) {
            $this->log("No new emails detected in import data.");
        }
        if ($changed_emails) {
            $changed_emails_count = count($changed_emails);
            $this->log("Some attributes have changed for {$changed_emails_count} emails, but won't adjust that.");
        }
        if ($obsolete_emails) {
            $obsolete_emails_count = count($obsolete_emails);
            $this->log("{$obsolete_emails_count} emails are not listed in input, but won't delete.");
        }

        /**********************************************
         **           SYNC CONTACT ADDRESSES         **
         **********************************************/
        $this->extractCurrentDetails($model, $present_model, 'address');
        [$new_addresses, $changed_addresses, $obsolete_addresses] = $present_model->diffAddresses($model, ['location_type', 'organization_name', 'house_number', 'id', 'country_id']);
        foreach ($new_addresses as $address) {
            /** @var \CRM_Committees_Model_Address $address */
            $person = $address->getContact($present_model);
            $address_data = $address->getData();
            $address_data['contact_id'] = $person->getAttribute('contact_id');
            $address_data['is_primary'] = 1;
            $address_string = $address_data['street_address'] ?? '';
            if (!empty($address_data['supplemental_address_1'])) $address_string .= "|{$address_data['supplemental_address_1']}";
            if (!empty($address_data['postal_code'])) $address_string .= "|{$address_data['postal_code']}";
            if (!empty($address_data['city'])) $address_string .= "|{$address_data['city']}";

            if (!$person->getAttribute('is_new')) {
                // newly created contact, add address
                $address_data['contact_id'] = $person->getAttribute('contact_id');
                $this->callApi3('Address', 'create', $address_data);
                $this->log("Added address '{$address_string}' to new contact [#{$address_data['contact_id']}]");
            } else {
                // existing contact, add TODO
                $this->log("TODO: add new address '{$address_string}' to contact [#{$address_data['contact_id']}]?");
            }
        }
        if (!$new_addresses) {
            $this->log("No new addresses detected in import data.");
        }
        foreach ($changed_addresses as $changed_address) {
            $person = $changed_address->getContact($present_model);
            if ($person) {
                $address_contact_id = $person->getAttribute('contact_id');
                $differing_attributes = explode(',', $changed_address->getAttribute('differing_attributes'));
                $differing_values = $changed_address->getAttribute('differing_values');
                foreach ($differing_attributes as $differing_attribute) {
                    if ($differing_attribute == 'supplemental_address_1') continue; // we don't want to add that to existing address
                    $this->log("TODO: Change address attribute '{$differing_attribute}' of contact [#{$address_contact_id}] from '{$differing_values[$differing_attribute][0]}' to '{$differing_values[$differing_attribute][1]}'?");
                }
            }
        }
        if ($obsolete_addresses) {
            $obsolete_addresses_count = count($obsolete_addresses);
            $this->log("{$obsolete_addresses_count} addresses are not listed in input, but won't delete.");
        }

        /**********************************************
         **        SYNC COMMITTEE MEMBERSHIPS        **
         **           (read: 'employments')          **
         **********************************************/

        // extract current memberships
        $this->extractCurrentMemberships($model, $present_model);
        $this->log(count($present_model->getAllMemberships()) . " existing employments identified in CiviCRM.");

        $ignore_attributes = ['relationship_id', 'relationship_type_id', 'start_date', 'committee_name', 'description', 'represents']; // todo: fine-tune
        [$new_memberships, $changed_memberships, $obsolete_memberships] = $present_model->diffMemberships($model, $ignore_attributes, ['id']);
        // first: disable absent (deleted)
        foreach ($obsolete_memberships as $membership) {
            /** @var CRM_Committees_Model_Membership $membership */
            // this membership needs to be ended/deactivated
            $relationship_id = $membership->getAttribute('relationship_id');

            // check if already disabled
            try {
                $is_enabled = $this->callApi3('Relationship', 'getvalue', [
                        'id' => $relationship_id,
                        'return' => 'is_active',
                ]);
                if ($is_enabled) {
                    $this->callApi3('Relationship', 'create', [
                            'id' => $relationship_id,
                            'is_active' => 0,
                            'end_date' => date('Y-m-d'),
                    ]);
                    $this->log("Disabled obsolete committee membership [{$membership->getAttribute('relationship_id')}].");
                }
            } catch (Exception $ex) {
                $this->log("Exception while disabling obsolete committee membership [{$membership->getAttribute('relationship_id')}].");
            }
        }

        // CREATE the new ones
        foreach ($new_memberships as $new_membership) {
            /** @var CRM_Committees_Model_Membership $new_membership */
            $person_id = $new_membership->getAttribute('contact_id');
            $person = $present_model->getPerson($person_id) ?? $model->getPerson($person_id);
            if (!$person) {
                $this->logError("Person of membership [{$new_membership->getID()}] not found.");
                continue;
            }
            $person_civicrm_id = $this->getIDTContactID($person->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
            $committee_civicrm_id = $new_membership->getCommittee()->getAttribute('contact_id');
            if (!$committee_civicrm_id) {
                $this->logError("Committee of membership [{$new_membership->getID()}] not found.");
                continue;
            }
            $this->callApi3('Relationship', 'create', [
                    'contact_id_a' => $person_civicrm_id,
                    'contact_id_b' => $committee_civicrm_id,
                    'relationship_type_id' => $new_membership->getAttribute('relationship_type_id'),
                    'is_active' => 1,
            ]);
            $this->log("Added new committee membership [{$person_civicrm_id}]<->[{$committee_civicrm_id}].");
        }
        $new_count = count($new_memberships);
        $this->log("{$new_count} new committee memberships created.");

        // THAT'S IT, WE'RE DONE
        if ($transaction) {
            $transaction->commit();
        }

        return true;
    }


    /**
     * Get the current membership (read: employments)
     *
     * @param CRM_Committees_Model_Model $requested_model
     *   the model to be synced to this CiviCRM
     *
     * @param CRM_Committees_Model_Model $present_model
     *   a model to add the current committees to, as extracted from the DB
     */
    protected function extractCurrentMemberships($requested_model, $present_model)
    {
        // get relationship type
        $employee_of_relationship_type_id = $this->getRelationshipTypeID('Employee of');

        // get all person IDs
        $existing_person_contact_ids = [];
        $current_persons = $present_model->getAllPersons();
        foreach ($current_persons as $current_person) {
            /** @var CRM_Committees_Model_Person $current_person */
            $current_person_contact_id = $current_person->getAttribute('contact_id');
            if ($current_person_contact_id) {
                $existing_person_contact_ids[$current_person->getID()] = $current_person_contact_id;
            }
        }
        $existing_person_contact_count = count($existing_person_contact_ids);

        // get all committee (employer) IDs
        $current_employer_contact_ids = [];
        $current_employers = $present_model->getAllCommittees();
        foreach ($current_employers as $current_employer) {
            /** @var CRM_Committees_Model_Committee $current_employer */
            $current_employer_contact_id = $current_employer->getAttribute('contact_id');
            if ($current_employer_contact_id) {
                $current_employer_contact_ids[$current_employer->getID()] = $current_employer_contact_id;
            }
        }
        $current_employer_contact_count = count($current_employer_contact_ids);

        // Extract the existing 'committee memberships' (read: employments)
        $this->log("Looking for work relationships between {$current_employer_contact_count} existing divisions and {$$existing_person_contact_count} existing persons.");
        $contact_civiID_to_poID = array_flip($existing_person_contact_ids);
        $committee_civiID_to_poID = array_flip($current_employer_contact_ids);
        $current_employments = \Civi\Api4\Relationship::get(FALSE)
                ->addSelect('value', 'label')
                ->addWhere('contact_id_a', 'IN', $existing_person_contact_ids)
                ->addWhere('contact_id_b', 'IN', $current_employer_contact_ids)
                ->addWhere('relationship_type_id', '=', $employee_of_relationship_type_id)
                ->addWhere('is_active', '=', true)
                ->setCheckPermissions(false)
                ->execute();
        foreach ($current_employments->getIterator() as $existing_employment) {
            $present_model->addCommitteeMembership([
               'contact_id' => $contact_civiID_to_poID['contact_id_a'],
               'committee_id' => $committee_civiID_to_poID['contact_id_b'],
             ]);
        }
    }

    /**
     * Get the current committees (here: structural divisions)
     *
     * @param CRM_Committees_Model_Model $requested_model
     *   the model to be synced to this CiviCRM
     *
     * @param CRM_Committees_Model_Model $present_model
     *   a model to add the current committees to, as extracted from the DB
     */
    protected function extractCurrentCommittees($requested_model, $present_model)
    {
        $committee_count_before = count($present_model->getAllCommittees());
        $divisions = \Civi\Api4\Contact::get(FALSE)
                ->addSelect('id', CRM_Committees_Implementation_PersonalOfficeSyncer::ORGANISATION_EKIR_ID_FIELD, 'display_name')
                ->addWhere(CRM_Committees_Implementation_PersonalOfficeSyncer::ORGANISATION_EKIR_ID_FIELD, 'IS NOT EMPTY')
                ->addWhere('contact_type', '=', 'Organization')
                ->execute();
        foreach ($divisions->getIterator() as $division) {
            $present_model->addCommittee([
                 'name' => $division['display_name'] ?? 'n/a',
                 'id' => $division[CRM_Committees_Implementation_PersonalOfficeSyncer::ORGANISATION_EKIR_ID_FIELD],
                 'contact_id' => $division['id']
            ]);
        }
    }

    /**
     * Quick hack: simply import all the entities, NO SYNCING!
     *
     * @deprecated replaced with real synchronisation
     *
     * @param CRM_Committees_Model_Model $model
     */
    protected function simpleImport($model)
    {
        // join addresses, emails
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
                        CRM_Committees_Implementation_PersonalOfficeSyncer::ORGANISATION_EKIR_ID_FIELD => $employer_ekir_id,
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

    /**
     * Try to derive the contact sub type from a given 'externe org. nr'
     *
     * @param $org_nummer
     * @return string
     */
    protected function getContactSubTypeFromId($org_nummer)
    {
        if (preg_match("/^[0-9]{6}$/", $org_nummer)) {
            return 'Kirchenkreis';
        } elseif (preg_match("/^[0-9]{8}$/", $org_nummer)) {
            return 'Kirchengemeinde';
        } else {
            return '';
        }
    }
}
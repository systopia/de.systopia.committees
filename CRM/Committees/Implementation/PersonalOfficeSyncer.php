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
        }

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
    public function syncModel($model, $transaction = false)
    {
        // first, make sure some stuff is there
        $this->registerIDTrackerType(self::CONTACT_TRACKER_TYPE, "Personal Office ID");

        // make sure Pfarrer*in contact sub type exists
        $this->createContactTypeIfNotExists(self::CONTACT_CONTACT_TYPE_NAME, self::CONTACT_CONTACT_TYPE_LABEL, 'Individual');

        if (class_exists('CRM_Gmv_CustomData')) {
            $customData = new CRM_Gmv_CustomData(E::LONG_NAME);
            $customData->syncOptionGroup(E::path('resources/PersonalOffice/option_group_pfarrer_innen.json'));
            $customData->syncCustomGroup(E::path('resources/PersonalOffice/custom_group_pfarrer_innen.json'));
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
         **********************************************/

        // now extract current committees and run the diff
        $this->extractCurrentCommittees($model, $present_model);
        [$new_committees, $changed_committees, $obsolete_committees] = $present_model->diffCommittees($model, ['contact_id', 'end_date', 'start_date', 'id']);
        if ($new_committees) {
            throw new Exception("Dealing with new or discontinued committees not implemented.");
        }
        // add warnings:
        foreach ($changed_committees as $changed_committee) {
            /* @var CRM_Committees_Model_Committee $changed_committee */
            $differing_attributes = explode(',', $changed_committee->getAttribute('differing_attributes'));
            $differing_values = $changed_committee->getAttribute('differing_values');
            $changed_committee_contact_id = $changed_committee->getAttribute('contact_id');
            $changed_committee_name = $changed_committee->getAttribute('name');
            foreach ($differing_attributes as $differing_attribute) {
                if ($differing_attribute == 'name' && $changed_committee_contact_id) {
                    // update committee name
                    $this->callApi3('Contact', 'create', [
                            'contact_id' => $changed_committee_contact_id,
                            'organization_name' => $changed_committee_name
                    ]);
                    $this->log("Name of committee [{$changed_committee->getID()}] was changed from {$differing_values[$differing_attribute][0]} to {$differing_values[$differing_attribute][1]}.");
                } else {
                    $this->log("Committee [{$changed_committee->getID()}] has changed '{$differing_attribute}' from {$differing_values[$differing_attribute][0]} to {$differing_values[$differing_attribute][1]}. Please adjust manually");
                }
            }
        }
        if ($obsolete_committees) {
            $this->log("There are obsolete committees, but they will not be removed.");
        }

        /**********************************************
         **           SYNC BASE CONTACTS            **
         **********************************************/
        $this->log("Syncing " . count($model->getAllPersons()) . " data sets...");

        // join addresses, emails, phones
        $model->joinAddressesToPersons();
        $model->joinEmailsToPersons();
        $model->joinPhonesToPersons();

        // SKIP : apply custom adjustments to the persons
//        foreach ($model->getAllPersons() as $person) {
//            /** @var CRM_Committees_Model_Person $person */
//            $person_data = $person->getData();
//            $person->setAttribute('gender_id', $this->getGenderId($person_data));
//            $person->setAttribute('suffix_id', $this->getSuffixId($person_data));
//            $person->setAttribute('prefix_id', $this->getPrefixId($person_data));
//        }

        // then compare to current model and apply changes
        $this->extractCurrentContacts($model, $present_model);
        [$new_persons, $changed_persons, $obsolete_persons] = $present_model->diffPersons($model, ['contact_id', 'formal_title', 'prefix', 'street_address', 'house_number', 'postal_code', 'city', 'email', 'supplemental_address_1', 'phone', 'gender_id', 'prefix_id', 'suffix_id']);

        // create missing contacts
        //$person_custom_field_mapping = $this->getPersonCustomFieldMapping($model);
        foreach ($new_persons as $new_person) {
            /** @var CRM_Committees_Model_Person $new_person */
            $person_data = $new_person->getDataWithout(['id']);
            $person_data['contact_type'] = 'Individual';
            $person_data['source'] = 'SESSION-' . date('Y');
//            if ($person_custom_field_mapping) {
//                foreach ($person_custom_field_mapping as $person_property => $contact_custom_field) {
//                    $person_data[$contact_custom_field] = $new_person->getAttribute($person_property);
//                }
//            }
            $result = $this->callApi3('Contact', 'create', $person_data);

            // contact post-processing
            $this->setIDTContactID($new_person->getID(), $result['id'], self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
            $new_person->setAttribute('contact_id', $result['id']);

            // add to the present model
            $present_model->addPerson($new_person->getData());

            $this->log("Session Contact [{$new_person->getID()}] created with CiviCRM-ID [{$result['id']}].");
        }
        if (!$new_persons) {
            $this->log("No new contacts detected in import data.");
        }

        // apply changes to existing contacts
        foreach ($changed_persons as $current_person) {
            /** @var CRM_Committees_Model_Person $current_person */
            $person_update = [
                    'id' => $this->getIDTContactID($current_person->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX),
            ];
            $differing_attributes = explode(',', $current_person->getAttribute('differing_attributes'));
            $changed_person = $model->getPerson($current_person->getID());
            foreach ($differing_attributes as $differing_attribute) {
                $person_update[$differing_attribute] = $changed_person->getAttribute($differing_attribute);
            }
            $result = $this->callApi3('Contact', 'create', $person_update);
            $this->log("Session Contact [{$current_person->getID()}] (CID [{$person_update['id']}]) updated, changed: " . $current_person->getAttribute('differing_attributes'));
        }

        // note obsolete contacts
        if (!empty($obsolete_persons)) {
            $obsolete_person_count = count($obsolete_persons);
            $this->log("There are {$obsolete_person_count} relevant persons in CiviCRM that are not listed in the new data set. Those will *not* be deleted:");
            // clear lobby data from obsolete persons
            foreach ($obsolete_persons as $obsolete_person) {
                /** @var CRM_Committees_Model_Person $obsolete_person */
                $contact_id = $this->getIDTContactID($obsolete_person->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
                if ($contact_id) {
                    $contact = civicrm_api3('Contact', 'getsingle', ['id' => $contact_id, 'return' => 'id,display_name']);
                    $this->log("Not deleting obsolete contact [#{$contact['id']}]: " . $this->obfuscate($contact['display_name']));
                } else {
                    $this->log("Couldn't find person [{$obsolete_person->getID()}], so not deleting.");
                }
            }
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
            if ($person) {
                $email_data['contact_id'] = $person->getAttribute('contact_id');
                $this->callApi3('Email', 'create', $email_data);
                $shortened_email_data = $this->obfuscate($email_data['email']);
                $this->log("Added email '{$shortened_email_data}' to contact [{$email_data['contact_id']}]");
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
         **           SYNC CONTACT PHONES            **
         **********************************************/
        $this->extractCurrentDetails($model, $present_model, 'phone');
        [$new_phones, $changed_phones, $obsolete_phones] = $present_model->diffPhones($model, ['location_type', 'id'], ['phone', 'contact_id']);
        foreach ($new_phones as $phone) {
            /** @var CRM_Committees_Model_Phone $phone */
            $phone_data = $phone->getData();
            //$phone_data['location_type_id'] = $this->getAddressLocationType(CRM_Committees_Implementation_KuerschnerCsvImporter::LOCATION_TYPE_BUNDESTAG);
            $phone_data['is_primary'] = 1;
            //$phone_data['phone_type_id'] = $this->getPhoneTypeId($phone_data);
            $person = $phone->getContact($present_model);
            if ($person) {
                $phone_data['contact_id'] = $person->getAttribute('contact_id');
                $this->callApi3('Phone', 'create', $phone_data);
                $shortened_phone_data = $this->obfuscate($phone_data['phone']);
                $this->log("Added phone '{$shortened_phone_data} to contact [{$phone_data['contact_id']}]");
            }
        }
        if (!$new_phones) {
            $this->log("No new phones detected in import data.");
        }
        if ($changed_phones) {
            $changed_phones_count = count($changed_phones);
            $this->log("Some attributes have changed for {$changed_phones_count} phones, but won't adjust that.");
        }
        if ($obsolete_phones) {
            $obsolete_phones_count = count($obsolete_phones);
            $this->log("{$obsolete_phones_count} phones are not listed in input, but won't delete.");
        }

        /**********************************************
         **           SYNC CONTACT ADDRESSES         **
         **********************************************/
        $this->extractCurrentDetails($model, $present_model, 'address');
        [$new_addresses, $changed_addresses, $obsolete_addresses] = $present_model->diffAddresses($model, ['location_type', 'organization_name', 'house_number', 'id']);
        $unwanted_relationship_counter = 0;
        foreach ($new_addresses as $address) {
            /** @var \CRM_Committees_Model_Address $address */
            $address_data = $address->getData();
            //$address_data['location_type_id'] = $this->getAddressLocationType(CRM_Committees_Implementation_KuerschnerCsvImporter::LOCATION_TYPE_BUNDESTAG);
            $address_data['is_primary'] = 1;
            $person = $address->getContact($present_model);
            if ($person) {
                $address_data['contact_id'] = $person->getAttribute('contact_id');
                $last_relationship_id = (int) CRM_Core_DAO::singleValueQuery("SELECT MAX(id) FROM civicrm_relationship;");
                $this->callApi3('Address', 'create', $address_data);
                $shortened_address_data = $this->obfuscate($address_data['street_address']) . '/' . $address_data['postal_code'];
                $this->log("Added address '{$shortened_address_data}' to contact [{$address_data['contact_id']}]");
            }
        }
//        if ($unwanted_relationship_counter) {
//            $this->log("{$unwanted_relationship_counter} unwanted relationships (employee of) have been deleted");
//        }
        if (!$new_addresses) {
            $this->log("No new addresses detected in import data.");
        }
        if ($changed_addresses) {
            $changed_addresses_count = count($changed_addresses);
            $this->log("Some attributes have changed for {$changed_addresses_count} addresses, but won't adjust that.");
        }
        if ($obsolete_addresses) {
            $obsolete_addresses_count = count($obsolete_addresses);
            $this->log("{$obsolete_addresses_count} addresses are not listed in input, but won't delete.");
        }

        /**********************************************
         **        SYNC COMMITTEE MEMBERSHIPS        **
         **********************************************/
        // adjust memberships
        $relationship_type_id = $this->getRelationshipTypeID('is_committee_member_of');

        foreach ($model->getAllMemberships() as $membership) {
            /** @var $membership CRM_Committees_Model_Membership */

            // 1) set the relationship types (and consider active)
            $membership->setAttribute('relationship_type_id', $relationship_type_id);
            $membership->setAttribute('is_active', 1);
        }

        // extract current memberships
        $this->addCurrentMemberships($model, $present_model);
        //$this->log(count($present_model->getAllMemberships()) . " existing committee memberships identified in CiviCRM.");

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
//            $person = $new_membership->getPerson();
            $person_id = $new_membership->getAttribute('contact_id');
            $person = $present_model->getPerson($person_id) ?? $model->getPerson($person_id);
            if (!$person) {
                $this->logError("Person of membership [{$new_membership->getID()}] not found.");
                continue;
            }
            $person_civicrm_id = $this->getIDTContactID($person->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
            $committee_id = $this->getIDTContactID($new_membership->getCommittee()->getID(), self::CONTACT_TRACKER_TYPE, self::COMMITTEE_TRACKER_PREFIX);
            if (!$committee_id) {
                $this->logError("Committee of membership [{$new_membership->getID()}] not found.");
                continue;
            }
            $this->callApi3('Relationship', 'create', [
                    'contact_id_a' => $person_civicrm_id,
                    'contact_id_b' => $committee_id,
                    'relationship_type_id' => $new_membership->getAttribute('relationship_type_id'),
                    'is_active' => 1,
            ]);
            $this->log("Added new committee membership [{$person_civicrm_id}]<->[{$committee_id}].");
        }
        $new_count = count($new_memberships);
        $this->log("{$new_count} new committee memberships created.");

        // THAT'S IT, WE'RE DONE
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
<?php
/*-------------------------------------------------------+
| SYSTOPIA Committee Framework                           |
| Copyright (C) 2021-23 SYSTOPIA                         |
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
class CRM_Committees_Implementation_SessionSyncer extends CRM_Committees_Plugin_Syncer
{
    use CRM_Committees_Tools_IdTrackerTrait;
    use CRM_Committees_Tools_XcmTrait;

    const CONTACT_TRACKER_TYPE = 'session';
    const CONTACT_TRACKER_PREFIX = 'SESSION-';
    const COMMITTEE_TRACKER_PREFIX = 'GREMIUM-';
    const XCM_PERSON_PROFILE = 'session_person';
    const XCM_COMMITTEE_PROFILE = 'session_organisation';

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
        // to be sure, disable the execution time limit
        ini_set('max_execution_time', '0');

        // then, make sure some stuff is there
        $this->registerIDTrackerType(self::CONTACT_TRACKER_TYPE, "Session Person ID");
        $this->registerIDTrackerType(self::COMMITTEE_TRACKER_PREFIX, "Session Gremium ID");
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
        [$new_committees, $changed_committees, $obsolete_committees] = $present_model->diffCommittees($model, ['contact_id', 'handle', 'name_short', 'start_date']);
        if ($new_committees) {
            throw new Exception("Dealing with new or discontinued committees not implemented.");
        }
        // add warnings:
        foreach ($changed_committees as $changed_committee) {
            /* @var CRM_Committees_Model_Committee $changed_committee */
            $differing_attributes = explode(',', $changed_committee->getAttribute('differing_attributes'));
            $differing_values = $changed_committee->getAttribute('differing_values');
            foreach ($differing_attributes as $differing_attribute) {
                $this->log("Committee [{$changed_committee->getID()}] has changed '{$differing_attribute}' from {$differing_values[$differing_attribute][0]} to {$differing_values[$differing_attribute][1]}. Please adjust manually");
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
        [$new_persons, $changed_persons, $obsolete_persons] = $present_model->diffPersons($model, ['contact_id', 'formal_title', 'prefix', 'street_address', 'house_number', 'postal_code', 'city', 'email', 'supplemental_address_1', 'phone']);

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
            $this->log("There are {$obsolete_person_count} relevant persons in CiviCRM that are not listed in the new data set. Those will *not* be deleted.");
            // clear lobby data from obsolete persons
            foreach ($obsolete_persons as $obsolete_person) {
                /** @var CRM_Committees_Model_Person $obsolete_person */
                $contact_id = $this->getIDTContactID($obsolete_person->getID(), self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
                $this->log("TODO: What to do with obsolete contact [{$contact_id}]?");
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
            $this->log("Some attributes have changed for {$changed_emails_count}, be we won't adjust that.");
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
            $this->log("Some attributes have changed for {$changed_phones_count}, be we won't adjust that.");
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
//
//                // check if the shared address (master_id) has created a relationship, and delete it (not wanted)
//                $new_relationship_id = (int) CRM_Core_DAO::singleValueQuery("SELECT MAX(id) FROM civicrm_relationship;");
//                if ($new_relationship_id > $last_relationship_id) {
//                    // delete it (those?)
//                    $unwanted_relationships = CRM_Core_DAO::executeQuery("
//                        SELECT relationship.id AS relationship_id
//                        FROM civicrm_relationship relationship
//                        WHERE relationship.id > %1
//                          AND relationship.contact_id_a = %2", [
//                            1 => [$last_relationship_id, 'Integer'],
//                            2 => [$person->getAttribute('contact_id'), 'Integer'],
//                    ]);
//                    while ($unwanted_relationships->fetch()) {
//                        $this->callApi3('Relationship', 'delete', ['id' => $unwanted_relationships->relationship_id]);
//                        $unwanted_relationship_counter++;
//                    }
//                }
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
            $this->log("Some attributes have changed for {$changed_addresses_count}, be we won't adjust that.");
        }
        if ($obsolete_addresses) {
            $obsolete_addresses_count = count($obsolete_addresses);
            $this->log("{$obsolete_addresses_count} addresses are not listed in input, but won't delete.");
        }

        /**********************************************
         **        SYNC COMMITTEE MEMBERSHIPS        **
         **********************************************/
        // adjust memberships
        foreach ($model->getAllMemberships() as $membership) {
            /** @var $membership CRM_Committees_Model_Membership */

            // 1) set the relationship types (and consider active)
            $membership->setAttribute('relationship_type_id', $this->getRelationshipTypeIdForMembership($membership));
            $membership->setAttribute('is_active', 1);

            // 2) adjust functions
            if ($membership->getAttribute('functions')) {
                // make sure the fields are there
                $political_functions = $this->extractPoliticalFunctions($membership, false);
                sort($political_functions);

                $membership->setAttribute('functions', $political_functions);
            }
        }

        // extract current memberships
        $this->addCurrentMemberships($model, $present_model);
        //$this->log(count($present_model->getAllMemberships()) . " existing committee memberships identified in CiviCRM.");

        $ignore_attributes = ['committee_name', 'role', 'relationship_id', 'relationship_type_id']; // todo: fine-tune
        [$new_memberships, $changed_memberships, $obsolete_memberships] = $present_model->diffMemberships($model, $ignore_attributes);
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
            $person_civicrm_id = $this->getIDTContactID($new_membership->getPerson()->getID(), self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX);
            switch ($new_membership->getCommittee()->getAttribute('type')) {
                case CRM_Committees_Implementation_KuerschnerCsvImporter::COMMITTEE_TYPE_PARLIAMENTARY_COMMITTEE:
                    $tracker_prefix = self::ID_TRACKER_PREFIX_COMMITTEE;
                    $political_functions = [];
                    break;

                case self::COMMITTEE_TYPE_PARLIAMENT:
                    $tracker_prefix = self::ID_TRACKER_PREFIX_PARLIAMENT;
                    $political_functions = [];
                    break;

                default:
                case CRM_Committees_Implementation_KuerschnerCsvImporter::COMMITTEE_TYPE_PARLIAMENTARY_GROUP:
                    $tracker_prefix = self::ID_TRACKER_PREFIX_FRAKTION;
                    $political_functions = $new_membership->getAttribute('functions');
                    sort($political_functions);
                    break;
            }
            $committee_id = $this->getIDTContactID($new_membership->getCommittee()->getID(), self::ID_TRACKER_TYPE, $tracker_prefix);
            $this->callApi3('Relationship', 'create', [
                    'contact_id_a' => $person_civicrm_id,
                    'contact_id_b' => $committee_id,
                    'relationship_type_id' => $new_membership->getAttribute('relationship_type_id'),
                    'is_active' => 1,
                    'description' => substr($new_membership->getAttribute('description'), 0, 255),
                    $political_functions_field => $political_functions,
            ]);
            $this->log("Added new committee membership [{$person_civicrm_id}]<->[{$committee_id}].");
        }
        $new_count = count($new_memberships);
        $this->log("{$new_count} new committee memberships created.");


        // UPDATE the existing ones (if necessary)
        foreach ($changed_memberships as $changed_membership) {
            /** @var CRM_Committees_Model_Membership $changed_membership */
            // extract update data
            $political_functions = [];
            $membership_type = $changed_membership->getCommittee()->getAttribute('type');

            // update description
            $requested_membership = $model->getCommitteeMembership(
                    $changed_membership->getAttribute(CRM_Committees_Model_Model::CORRESPONDING_ENTITY_ID_KEY));
            $new_description = $requested_membership->getAttribute('description');

            // update functions
            if ($membership_type == CRM_Committees_Implementation_KuerschnerCsvImporter::COMMITTEE_TYPE_PARLIAMENTARY_GROUP) {
                $political_functions = $this->extractPoliticalFunctions($requested_membership);
                sort($political_functions);
            }
            $this->callApi3('Relationship', 'create', [
                    'id' => $changed_membership->getAttribute('relationship_id'),
                    'description' => substr($new_description, 0, 255),
                    'is_active' => $requested_membership->getAttribute('is_active'),
                    $political_functions_field => $political_functions,
            ]);
            $this->log("Adjusted minor change for committee membership [#{$changed_membership->getAttribute('relationship_id')}].");
        }

        // THAT'S IT, WE'RE DONE
        $this->log("If you're using this free module, send some grateful thoughts to OXFAM Germany.");
        // THAT'S IT, WE'RE DONE
    }



    /**
     * Extract the currently imported contacts from CiviCRM and add them to the 'present model'
     *
     * @param CRM_Committees_Model_Model $requested_model
     *   the model to be synced to this CiviCRM
     *
     * @param CRM_Committees_Model_Model $present_model
     *   a model to add the current contacts to, as extracted from the DB
     */
    protected function extractCurrentContacts($requested_model, $present_model)
    {
        // add existing contacts
        $existing_contacts = $this->getContactIDtoTids(self::CONTACT_TRACKER_TYPE, self::CONTACT_TRACKER_PREFIX);
        //$person_custom_field_mapping = $this->getPersonCustomFieldMapping($requested_model);
        if ($existing_contacts) {
            $contacts_found = $this->callApi3('Contact', 'get', [
                    'contact_type' => 'Individual',
                    'id' => ['IN' => array_keys($existing_contacts)],
                    'return' => 'id,contact_id,first_name,last_name,gender_id,prefix_id',
                    'option.limit' => 0,
            ]);
            foreach ($contacts_found['values'] as $contact_found) {
                $present_contact_id = $existing_contacts[$contact_found['id']][0];
                $existing_person = [
                        'id'           => substr($present_contact_id, strlen(self::CONTACT_TRACKER_PREFIX)),
                        'contact_id'   => $contact_found['id'],
                        'first_name'   => $contact_found['first_name'],
                        'last_name'    => $contact_found['last_name'],
                        'gender_id'    => $contact_found['gender_id'],
                        'prefix'       => $contact_found['prefix'],
                ];
//                foreach ($person_custom_field_mapping as $person_property => $custom_field) {
//                    $existing_person[$person_property] = $contact_found[$custom_field];
//                }
                $present_model->addPerson($existing_person);
            }
        }
    }

    /**
     * Get the current committees as a partial model
     *
     * @param CRM_Committees_Model_Model $requested_model
     *   the model to be synced to this CiviCRM
     *
     * @param CRM_Committees_Model_Model $present_model
     *   a model to add the current committees to, as extracted from the DB
     */
    protected function extractCurrentCommittees($requested_model, $present_model)
    {
        // add existing committees
        $existing_committees = $this->getContactIDtoTids(self::CONTACT_TRACKER_TYPE, self::COMMITTEE_TRACKER_PREFIX);
        if ($existing_committees) {
            $committees_found = $this->callApi3('Contact', 'get', [
                    'contact_type' => 'Organization',
                    'id' => ['IN' => array_keys($existing_committees)],
                    'return' => 'id,organization_name',
                    'option.limit' => 0,
            ]);
            foreach ($committees_found['values'] as $committee_found) {
                $present_committee_id = $existing_committees[$committee_found['id']][0];
                $present_model->addCommittee([
                         'name'       => $committee_found['organization_name'],
                         'id'         => substr($present_committee_id, strlen(self::COMMITTEE_TRACKER_PREFIX)),
                         'contact_id' => $committee_found['id'],
                 ]);
            }
        }
    }

    /**
     * Extract the currently imported contacts from the CiviCRMs and add them to the 'present model'
     *
     * @param CRM_Committees_Model_Model $requested_model
     *   the model to be synced to this CiviCRM
     *
     * @param CRM_Committees_Model_Model $present_model
     *   a model to add the current contacts to, as extracted from the DB
     *
     * @param string $type
     *   phone, email or address
     *
     * @param array $formatters
     *   list of formatters to be applied to the extracted value
     */
    protected function extractCurrentDetails($requested_model, $present_model, $type, $formatters = [])
    {
        // some basic configurations for the different types
        $load_attributes = [
                'email' => ['contact_id', 'email', 'location_type_id'],
                'phone' => ['contact_id', 'phone', 'location_type_id', 'phone_type_id', 'phone_numeric'],
                'address' => ['contact_id', 'street_address', 'postal_code', 'city', 'location_type_id'],
                'website' => ['contact_id', 'url', 'website_type_id'],
        ];
        $copy_attributes = [
                'email' => ['email'],
                'phone' => ['phone', 'phone_numeric'],
                'address' => ['street_address', 'postal_code', 'city', 'supplemental_address_1', 'supplemental_address_2', 'supplemental_address_3'],
                'website' => ['url', 'website_type_id'],
        ];

        // check with all known CiviCRM contacts
        $contact_id_to_person_id = [];
        foreach ($present_model->getAllPersons() as $person) {
            /** @var CRM_Committees_Model_Person $person */
            $contact_id = (int) $person->getAttribute('contact_id');
            if ($contact_id) {
                $contact_id_to_person_id[$contact_id] = $person->getID();
            }
        }

        // stop here if there's no known contacts
        if (empty($contact_id_to_person_id)) {
            return [];
        }

        // load the given attributes
        $existing_details = $this->callApi3($type, 'get', [
                'contact_id' => ['IN' => array_keys($contact_id_to_person_id)],
                'return' => implode(',', $load_attributes[$type]),
                'option.limit' => 0,
        ]);

        // strip duplicates
        $data_by_id = [];
        foreach ($existing_details['values'] as $detail) {
            $person_id = $contact_id_to_person_id[$detail['contact_id']];
            $data = ['contact_id' => $person_id];
            foreach ($copy_attributes[$type] as $attribute) {
                if (isset($detail[$attribute]) && $detail[$attribute]) {
                    foreach ($formatters as $formatter) {
                        switch ($formatter) {
                            case 'strtolower':
                                $detail[$attribute] = strtolower($detail[$attribute]);
                                break;
                            default:
                                // do nothing
                        }
                    }
                    $data[$attribute] = $detail[$attribute];
                }
            }
            $key = implode('##', $data);
            $data_by_id[$key] = $data;
        }

        // finally, add all to the model
        foreach ($data_by_id as $data) {
            switch ($type) {
                case 'phone':
                    $present_model->addPhone($data);
                    break;
                case 'email':
                    $present_model->addEmail($data);
                    break;
                case 'address':
                    $present_model->addAddress($data);
                    break;
                default:
                    throw new Exception("Unknown type {$type} for extractCurrentDetails function.");
            }
        }
    }












    /**
     * OBSOLETE: simply import all the entities, NO SYNCING!
     *
     * @deprecated replaced by synchronisation
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
            $gremium_id = $this->runXCM($data, self::XCM_COMMITTEE_PROFILE);
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
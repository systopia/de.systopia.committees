<?php
/*-------------------------------------------------------+
| SYSTOPIA Committee Framework                           |
| Copyright (C) 2021-2022 SYSTOPIA                       |
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
 * Syncer for KuerschnerCsvImporter parliamentary committe model
 */
class CRM_Committees_Implementation_OxfamSimpleSync extends CRM_Committees_Plugin_Syncer
{
    use CRM_Committees_Tools_IdTrackerTrait;
    use CRM_Committees_Tools_ContactGroupTrait;

    /** @var string committee.type value for parliamentary committee (Ausschuss) */
    const COMMITTEE_TYPE_PARLIAMENTARY_COMMITTEE = 'parliamentary_committee';

    /** @var string committee.type value for parliamentary group (Fraktion) */
    const COMMITTEE_TYPE_PARLIAMENTARY_GROUP = 'parliamentary_group';

    const ID_TRACKER_TYPE = 'kuerschners';
    const ID_TRACKER_PREFIX = 'KUE-';
    const ID_TRACKER_PREFIX_COMMITTEE = 'BUND-AUSSCHUSS-';  // todo: adjustment needed, if same importer should be used for other parliaments
    const ID_TRACKER_PREFIX_FRAKTION = 'BUND-FRAKTION-';    // todo: adjustment needed, if same importer should be used for other parliaments
    const CONTACT_SOURCE = 'Kuerschners Bundestag';         // todo: adjustment needed, if same importer should be used for other parliaments
    const COMMITTE_SUBTYPE_NAME = 'Committee';
    const COMMITTE_SUBTYPE_LABEL = 'Gremium';

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

        /**************************************
         **        RUN SYNCHRONISATION       **
         **************************************/

        /** @var $present_model CRM_Committees_Model_Model this model will contain the data currently present in the DB  */
        $present_model = new CRM_Committees_Model_Model();

        /**********************************************
         **               SYNC COMMITTEES            **
         **********************************************/
        // first: apply custom adjustments to the committees
        foreach ($model->getAllCommittees() as $committee) {
            if ($committee->getAttribute('type') == self::COMMITTEE_TYPE_PARLIAMENTARY_GROUP) {
                $new_committee_name = $this->getFraktionName($committee->getAttribute('name'));
                $committee->setAttribute('name', $new_committee_name);
            }
        }

        // now extract current committees and run the diff
        $this->extractCurrentCommittees($model, $present_model);
        [$new_committees, $changed_committees, $obsolete_committees] = $present_model->diffCommittees($model, ['contact_id']);
        if ($new_committees) {
            foreach ($new_committees as $new_committee) {
                /** @var CRM_Committees_Model_Committee $new_committee */
                $committee_name = $new_committee->getAttribute('name');
                if ($new_committee->getAttribute('type') == self::COMMITTEE_TYPE_PARLIAMENTARY_GROUP) {
                    $tracker_prefix = self::ID_TRACKER_PREFIX_FRAKTION;
                } else {
                    $tracker_prefix = self::ID_TRACKER_PREFIX_COMMITTEE;
                }
                $result = $this->callApi3('Contact', 'create', [
                    'organization_name' => $committee_name,
                    'contact_sub_type' => $this->getCommitteeSubType(),
                    'contact_type' => 'Organization'
                ]);
                $this->setIDTContactID($new_committee->getID(), $result['id'], self::ID_TRACKER_TYPE, $tracker_prefix);
                $this->addContactToGroup($result['id'], $lobby_contact_group_id, true);
                $new_committee->setAttribute('contact_id', $result['id']);
                $present_model->addCommittee($new_committee->getData());
            }
            $this->log(count($new_committees) . " new committees created.");
        }
        // add warnings:
        if ($changed_committees) {
            if ($changed_committees) $this->log("TODO: There are changes to some committees, but these currently won't be applied.");
        }
        if ($obsolete_committees) {
            $this->log("There are obsolete committees, but they will not be removed.");
        }

        /**********************************************
         **               SYNC CONTACTS              **
         **********************************************/
        $this->log("Syncing " . count($model->getAllPersons()) . " individuals...");

        // first: apply custom adjustments to the committees
        foreach ($model->getAllPersons() as $person) {
            /** @var CRM_Committees_Model_Person $person */
            $person_data = $person->getData();
            $person->setAttribute('gender_id', $this->getGenderId($person_data));
            $person->setAttribute('suffix_id', $this->getSuffixId($person_data));
            $person->setAttribute('prefix_id', $this->getPrefixId($person_data));
        }

        // then compare to current model and apply changes
        $this->extractCurrentContacts($model, $present_model);
        [$new_persons, $changed_persons, $obsolete_persons] = $present_model->diffPersons($model, ['contact_id', 'formal_title']);

        // create missing contacts
        foreach ($new_persons as $new_person) {
            /** @var CRM_Committees_Model_Person $new_person */
            $person_data = $new_person->getDataWithout(['id']);
            $person_data['contact_type'] = $this->getContactType($person_data);
            $person_data['contact_sub_type'] = $this->getContactSubType($person_data);
            $person_data['source'] = self::CONTACT_SOURCE;
            $result = $this->callApi3('Contact', 'create', $person_data);
            $this->setIDTContactID($new_person->getID(), $result['id'], self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX);
            $this->log("Kürschner Contact [{$new_person->getID()}] created with CiviCRM-ID [{$result['id']}].");
        }

        // apply changes to existing contacts
        foreach ($changed_persons as $changed_person) {
            /** @var CRM_Committees_Model_Person $changed_person */
            $person_update = [
                'id' => $this->getIDTContactID($changed_person->getID(), self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX)
            ];
            $differing_attributes = explode(',', $changed_person->getAttribute('differing_attributes'));
            foreach ($differing_attributes as $differing_attribute) {
                $person_update[$differing_attribute] = $changed_person->getAttribute($differing_attribute);
            }
            $result = $this->callApi3('Contact', 'create', $person_update);
            $this->log("Kürschner Contact [{$changed_person->getID()}] (CID [{$person_update['id']}]) updated, changed: " . $changed_person->getAttribute('differing_attributes'));
        }

        // note obsolete contacts
        if (!empty($obsolete_persons)) {
            $obsolete_person_count = count($obsolete_persons);
            $this->log("There are {$obsolete_person_count} obsolete persons, but they will not be removed.");
        }

        $person_id_2_civicrm_id = [];
        $person_update_count = 0;
        $address_by_contact = $model->getEntitiesByID($model->getAllAddresses(), 'contact_id');
        $email_by_contact = $model->getEntitiesByID($model->getAllEmails(), 'contact_id');
        $phone_by_contact = $model->getEntitiesByID($model->getAllPhones(), 'contact_id');
        foreach ($model->getAllPersons() as $person) {
            /** @var CRM_Committees_Model_Person $person */
            $person_data = $person->getData();

            // look up ID
            $person_civicrm_id = $this->getIDTContactID($person->getID(), self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX);
            unset($person_data['id']);

            if ($person_civicrm_id) {
                $person_data['id'] = $person_civicrm_id;
            } else {
                unset($person_data['id']);
            }

            // prepare data for Contact.create
            $person_data['contact_type'] = $this->getContactType($person_data);
            $person_data['contact_sub_type'] = $this->getContactSubType($person_data);
            $person_data['source'] = self::CONTACT_SOURCE;
            $person_data['gender_id'] = $this->getGenderId($person_data);
            $person_data['prefix_id'] = $this->getPrefixId($person_data);
            $person_data['suffix_id'] = $this->getSuffixId($person_data);
            $result = $this->callApi3('Contact', 'create', $person_data);

            $person_civicrm_id = $result['id'];
            $this->setIDTContactID($person->getID(), $person_civicrm_id, self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX);
            if (empty($person_data['id'])) {
                $this->log("Kürschner Contact [{$person->getID()}] created with CiviCRM-ID [{$person_civicrm_id}].");
            } else {
                $this->log("Kürschner Contact [{$person->getID()}] updated (CiviCRM-ID [{$person_civicrm_id}]).");
            }
            $this->addContactToGroup($person_civicrm_id, $lobby_contact_group_id, true);

            // add addresses
            if (isset($address_by_contact[$person->getID()])) {
                foreach ($address_by_contact[$person->getID()] as $address) {
                    /** @var CRM_Committees_Model_Address $address */
                    $address_data = $address->getData();
                    $location_type_id = $this->getAddressLocationType($address_data['location_type']);
                    if ($location_type_id) {
                        $address_data['contact_id'] = $person_civicrm_id;
                        $address_data['is_primary'] = 1;
                        $address_data['supplemental_address_2'] = $address_data['supplemental_address_1'] ?? '';
                        $address_data['supplemental_address_1'] = $address_data['organization_name'];
                        $address_data['location_type_id'] = $location_type_id;
                        $address_data['master_id'] = $this->getParliamentAddressID($model);
                        unset($address_data['location_type']);
                        unset($address_data['is_primary']);
                        unset($address_data['organization_name']);
                        $result = $this->callApi3('Address', 'create', $address_data);
                    }
                }
            }

            // add phones
            if (isset($phone_by_contact[$person->getID()])) {
                foreach ($phone_by_contact[$person->getID()] as $phone) {
                    /** @var CRM_Committees_Model_Phone $phone */
                    $phone_data = $phone->getData();
                    unset($phone_data['location_type']);
                    $phone_data['contact_id'] = $person_civicrm_id;
                    $phone_data['is_primary'] = 1;
                    $phone_data['location_type_id'] = 'Work';
                    $phone_data['phone_type_id'] = 'Phone';
                    $result = $this->callApi3('Phone', 'create', $phone_data);
                }
            }

            // add emails
            if (isset($email_by_contact[$person->getID()])) {
                foreach ($email_by_contact[$person->getID()] as $email) {
                    /** @var CRM_Committees_Model_Email $email */
                    $email_data = $email->getData();
                    unset($email_data['location_type']);
                    $email_data['contact_id'] = $person_civicrm_id;
                    $email_data['is_primary'] = 1;
                    $email_data['location_type_id'] = 'Work';
                    $result = $this->callApi3('Email', 'create', $email_data);
                }
            }
            $person_update_count++;
        }
        // contact found/created
        $person_id_2_civicrm_id[$person->getID()] = $person_civicrm_id;
        $this->log("Syncing contacts complete, {$person_update_count} new contacts were created.");


        return;
        // SYNC MEMBERSHIPS
        $requested_memberships = $model->getAllMemberships();
        $this->log("Syncing " . count($requested_memberships) . " committee memberships...");

        $this->addCurrentMemberships($model, $present_model);
        $this->log(count($present_model->getAllMemberships()) . " existing committee memberships identified in CiviCRM.");

        $ignore_attributes = ['committee_name', 'relationship_id']; // todo: fine-tune
        [$new_memberships, $changed_memberships, $obsolete_memberships] = $present_model->diffMemberships($model, $ignore_attributes);
        // analysis: remove
        $new_membership = reset($new_memberships);
        $other_memberships = [];
        foreach ($obsolete_memberships as $obsolete_membership) {
            if ($obsolete_membership->getAttribute('contact_id') == $new_membership->getAttribute('contact_id')) {
                $other_memberships[] = $new_membership;
            }
        }
        $total_changes = count($new_memberships) + count($changed_memberships) + count($obsolete_memberships);
        $this->log("{$total_changes} additions or changes to committee memberships detected.");

        // first: disable absent (deleted)
        foreach ($obsolete_memberships as $membership) {
            /** @var CRM_Committees_Model_Membership $membership */
            // this membership needs to be ended/deactivated
            $this->callApi3('Relationship', 'create', [
                'id' => $membership->getAttribute('relationship_id'),
                'is_active' => 0,
            ]);
        }
        $obsolete_count = count($obsolete_memberships);
        $this->log("{$obsolete_count} obsolete committee memberships deactivated.");

        // then: create the new ones
        foreach ($new_memberships as $new_membership) {
            /** @var CRM_Committees_Model_Membership $new_membership */
            $person_civicrm_id = $this->getIDTContactID($new_membership->getPerson()->getID(), self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX);
            $committee_id = $committee_id_to_contact_id[$new_membership->getCommittee()->getID()];
            $relationship_type_id = $this->getRelationshipTypeIdForMembership($new_membership);
            $this->callApi3('Relationship', 'create', [
                'contact_id_a' => $person_civicrm_id,
                'contact_id_b' => $committee_id,
                'relationship_type_id' => $relationship_type_id,
                'is_active' => 1,
                'description' => $new_membership->getAttribute('role'),
            ]);
        }
        $new_count = count($new_memberships);
        $this->log("{$new_count} new committee memberships created.");
        $this->log("Syncing committee memberships complete.");

        // that's it
        $this->log("If you're using this free module, send some grateful thoughts to OXFAM Germany.");
    }

    /**
     * Extract the current committee memberships and add to the present_model
     *
     * @param CRM_Committees_Model_Model $requested_model
     *   the model to be synced to this CiviCRM
     *
     * @param CRM_Committees_Model_Model $present_model
     *   a model to add the current memberships to, as extracted from the DB
     */
    protected function addCurrentMemberships($requested_model, $present_model)
    {
        // get some basic data
        $role2relationship_type = $this->getRoleToRelationshipTypeIdMapping();
        $relationship_type_ids = array_unique(array_values($role2relationship_type));

        // get the current committees
        $parliament_ids = [$this->getParliamentContactID($requested_model)];
        $fraction_ids = array_keys($this->getContactIDtoTids(self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX_FRAKTION));
        $committee_ids = array_keys($this->getContactIDtoTids(self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX_COMMITTEE));
        $all_committee_ids = array_merge($parliament_ids, $fraction_ids, $committee_ids);

        // get the current memberships of these committees
        $committee_query = civicrm_api3('Relationship', 'get', [
            'option.limit' => 0,
            'relationship_type_id' => ['IN' => $relationship_type_ids],
            //'is_active' => 1, // also find inactive ones, otherwise we get issues with duplicates
            'contact_id_b' => ['IN' => $all_committee_ids]
        ]);

        // extract existing committee memberships
        $contactID_2_trackerIDs = $this->getContactIDtoTids(self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX);
        $contact_id_to_committee_name = array_flip($committee_name_to_contact_id);
        foreach ($committee_query['values'] as $committee_relationship) {
            $contact_id = $committee_relationship['contact_id_a'];
            $committee_id = $committee_relationship['contact_id_b'];
            $person_ids = $contactID_2_trackerIDs[$contact_id] ?? [];
            foreach ($person_ids as $person_id) {
                $present_model->addCommitteeMembership([
                       'contact_id'      => substr($person_id, strlen(self::ID_TRACKER_PREFIX)),
                       'committee_id'    => CRM_Committees_Implementation_KuerschnerCsvImporter::getCommitteeID($contact_id_to_committee_name[$committee_id]),
                       'committee_name'  => $contact_id_to_committee_name[$committee_id],
                       'type'            => $committee_name_to_type[$contact_id_to_committee_name[$committee_id]],
                       'role'            => $committee_relationship['description'] ?? '',
                       'relationship_id' => $committee_relationship['id']
                   ]);
            }
        }

        return $present_model;
    }



    /*****************************************************************************
     **                        PULL CURRENT DATA FOR SYNC                       **
     **         OVERWRITE THESE METHODS TO ADJUST TO YOUR DATA MODEL            **
     *****************************************************************************/

//    /**
//     * Get the contact IDs of the currently active committees
//     *
//     * @param CRM_Committees_Model_Model $model
//     *   the model to be synced to this CiviCRM
//     *
//     * @return array list of
//     *   committee_name => contact_id
//     */
//    protected function getCurrentCommittees($model)
//    {
//        $committee_name_to_contact_id = [];
//        foreach ($model->getAllCommittees() as $committee) {
//            /** @var $committee CRM_Committees_Model_Committee */
//            $committee_name = $committee->getAttribute('name');
//            $committee_search = civicrm_api3('Contact', 'get', [
//                'organization_name' => $committee_name,
//                'option.limit' => 1,
//                'contact_type' => 'Organization',
//                'option.sort' => 'id asc',
//            ]);
//            if (!empty($committee_search['id'])) {
//                $committee = reset($committee_search['values']);
//                $committee_name_to_contact_id[$committee_name] = $committee['id'];
//            } else {
//                $this->log("Committee '{$committee_name}' not found.", 'debug');
//            }
//        }
//
//        // log and exit
//        $total_committee_count = count($model->getAllCommittees());
//        $found_committee_count = count($committee_name_to_contact_id);
//        $this->log("Found {$found_committee_count} of {$total_committee_count} committees in the system.");
//        return $committee_name_to_contact_id;
//    }


    /**
     * Get the current committee memberships as a partial model
     *
     * @param CRM_Committees_Model_Model $requested_model
     *   the model to be synced to this CiviCRM
     *
     * @param CRM_Committees_Model_Model $present_model
     *   a model to add the current memberships to, as extracted from the DB
     *
     * @todo maybe we can extract a complete model of the current situation,
     *   and then have a generic diff routine, but that seems to exceed the budget at this point
     */
    protected function _old_addCurrentMemberships($requested_model, $present_model)
    {
        // get some basic data
        $parliament_id = $this->getParliamentContactID($requested_model);
        $role2relationship_type = $this->getRoleToRelationshipTypeIdMapping();
        $relationship_type_ids = array_unique(array_values($role2relationship_type));

        // get the current committees
        $committee_ids = [];
        $committee_name_to_contact_id = $this->getCurrentCommittees($requested_model);
        $committee_ids = array_values($committee_name_to_contact_id);
        $committee_ids[] = $parliament_id;

        // get committee name to type
        $committee_name_to_type = [];
        foreach ($requested_model->getAllCommittees() as $committee) {
            /** @var CRM_Committees_Model_Committee $committee */
            $committee_name_to_type[$committee->getAttribute('name')] = $committee->getAttribute('type');
        }

        // get the current memberships of these committees
        $committee_query = civicrm_api3('Relationship', 'get', [
            'option.limit' => 0,
            'relationship_type_id' => ['IN' => $relationship_type_ids],
            //'is_active' => 1, // also find inactive ones, otherwise we get issues with duplicates
            'contact_id_b' => ['IN' => $committee_ids]
        ]);

        // extract existing committee memberships
        $contactID_2_trackerIDs = $this->getContactIDtoTids(self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX);
        $contact_id_to_committee_name = array_flip($committee_name_to_contact_id);
        foreach ($committee_query['values'] as $committee_relationship) {
            $contact_id = $committee_relationship['contact_id_a'];
            $committee_id = $committee_relationship['contact_id_b'];
            $person_ids = $contactID_2_trackerIDs[$contact_id] ?? [];
            foreach ($person_ids as $person_id) {
                $present_model->addCommitteeMembership([
                    'contact_id'      => substr($person_id, strlen(self::ID_TRACKER_PREFIX)),
                    'committee_id'    => CRM_Committees_Implementation_KuerschnerCsvImporter::getCommitteeID($contact_id_to_committee_name[$committee_id]),
                    'committee_name'  => $contact_id_to_committee_name[$committee_id],
                    'type'            => $committee_name_to_type[$contact_id_to_committee_name[$committee_id]],
                    'role'            => $committee_relationship['description'] ?? '',
                    'relationship_id' => $committee_relationship['id']
                ]);
            }
        }

        return $present_model;
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
        $existing_committees = $this->getContactIDtoTids(self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX_COMMITTEE);
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
                     'type'       => self::COMMITTEE_TYPE_PARLIAMENTARY_COMMITTEE,
                     'id'         => substr($present_committee_id, strlen(self::ID_TRACKER_PREFIX_COMMITTEE)),
                     'contact_id' => $committee_found['id'],
                 ]);
            }
        }

        // add fractions
        $existing_fractions = $this->getContactIDtoTids(self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX_FRAKTION);
        if ($existing_fractions) {
            $fractions_found = $this->callApi3('Contact', 'get', [
                'contact_type' => 'Organization',
                'id' => ['IN' => array_keys($existing_fractions)],
                'return' => 'id,organization_name',
                'option.limit' => 0,
            ]);
            foreach ($fractions_found['values'] as $fraction_found) {
                $present_fraction_id = $existing_fractions[$fraction_found['id']][0];
                $present_model->addCommittee([
                     'name'       => $fraction_found['organization_name'],
                     'type'       => self::COMMITTEE_TYPE_PARLIAMENTARY_GROUP,
                     'id'         => substr($present_fraction_id, strlen(self::ID_TRACKER_PREFIX_FRAKTION)),
                     'contact_id' => $fraction_found['id'],
                 ]);
            }
        }
    }

    /**
     * Extract the currenlty imported contacts from the CiviCRMs and add them to the 'present model'
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
        $existing_contacts = $this->getContactIDtoTids(self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX);
        if ($existing_contacts) {
            $contacts_found = $this->callApi3('Contact', 'get', [
                'contact_type' => 'Individual',
                'id' => ['IN' => array_keys($existing_contacts)],
                'return' => 'id,contact_id,first_name,last_name,gender_id,prefix_id,suffix_id',
                'option.limit' => 0,
            ]);
            foreach ($contacts_found['values'] as $contact_found) {
                $present_contact_id = $existing_contacts[$contact_found['id']][0];
                $present_model->addPerson([
                      'id'           => substr($present_contact_id, strlen(self::ID_TRACKER_PREFIX)),
                      'contact_id'   => $contact_found['id'],
                      'first_name'   => $contact_found['first_name'],
                      'last_name'    => $contact_found['last_name'],
                      'gender_id'    => $contact_found['gender_id'],
                      'prefix_id'    => $contact_found['prefix_id'],
                      'suffix_id'    => $contact_found['suffix_id'],
                  ]);
            }
        }
    }


    /*****************************************************************************
     **                            DETAILS CUSTOMISATION                        **
     **         OVERWRITE THESE METHODS TO ADJUST TO YOUR DATA MODEL            **
     *****************************************************************************/

    /**
     * Get the right prefix ID for the given person data
     *
     * @param array $person_data
     *   list of the raw attributes coming from the model.
     */
    protected function getPrefixId($person_data)
    {
        if (empty($person_data['prefix_id'])) {
            return '';
        }

        $prefix_id = $person_data['prefix_id'];

        // map
        $mapping = [
            'Frau' => 'Frau',
            'Herrn' => 'Herr'
        ];
        if (isset($mapping[$prefix_id])) {
            $prefix_id = $mapping[$prefix_id];
        }

        $option_value = $this->getOrCreateOptionValue(['label' => $prefix_id], 'individual_prefix');
        return $option_value['value'];
    }

    /**
     * Get the right prefix string for the given CiviCRM prefix ID
     *
     * @param array $person_data
     *   list of the raw attributes coming from the model.
     */
    protected function getPrefixString($person_data)
    {
        if (empty($person_data['prefix_id'])) {
            return '';
        }

        $prefix_id = $person_data['prefix_id'];

        // map
        $mapping = [
            'Frau' => 'Frau',
            'Herrn' => 'Herr'
        ];
        if (isset($mapping[$prefix_id])) {
            $prefix_id = $mapping[$prefix_id];
        }

        $option_value = $this->getOrCreateOptionValue(['label' => $prefix_id], 'individual_prefix');
        return $option_value['value'];
    }


    /**
     * Get the right gender ID for the given person data
     *
     * @param array $person_data
     *   list of the raw attributes coming from the model.
     */
    protected function getGenderId(array $person_data)
    {
        if (empty($person_data['gender_id'])) {
            return '';
        }
        $gender_id = $person_data['gender_id'];

        // map
        $mapping = [
            'm' => 'männlich',
            'w' => 'weiblich'
        ];
        if (isset($mapping[$gender_id])) {
            $gender_id = $mapping[$gender_id];
        }

        $option_value = $this->getOrCreateOptionValue(['label' => $gender_id], 'gender');
        return $option_value['value'];
    }

    /**
     * Get the right gender ID for the given person data
     *
     * @param array $person_data
     *   list of the raw attributes coming from the model.
     */
    protected function getSuffixId(array $person_data)
    {
        if (empty($person_data['formal_title'])) {
            return '';
        }

        // no mapping, right?
        $suffix = $person_data['formal_title'];
        $option_value = $this->getOrCreateOptionValue(['label' => $suffix], 'individual_suffix');
        return $option_value['value'];
    }

    /**
     * Get the preferred contact type
     *
     * @param array $person_data
     *   list of the raw attributes coming from the model.
     *
     * @return string
     */
    protected function getContactType(array $person_data)
    {
        return 'Individual';
    }

    /**
     * Get the preferred contact type
     *
     * @param array $person_data
     *   list of the raw attributes coming from the model.
     *
     * @return string|null
     */
    protected function getContactSubType(array $person_data)
    {
        return null;
    }

    /**
     * Get the name of the currently imported parliament (CiviCRM Organization), e.g. "Bundestag"
     *
     * @param \CRM_Committees_Model_Model $model
     *   the model
     *
     * @return string name of the parliament
     */
    protected function getParliamentName($model)
    {
        static $parliament_name = null;
        if ($parliament_name === null) {
            foreach ($model->getAllAddresses() as $address) {
                /** @var \CRM_Committees_Model_Address $address */
                $location_type = $address->getAttribute('location_type');
                if ($location_type == CRM_Committees_Implementation_KuerschnerCsvImporter::LOCATION_TYPE_BUNDESTAG) {
                    $potential_parliament_name = $address->getAttribute('organization_name');
                    if (!empty($potential_parliament_name)) {
                        $parliament_name = $potential_parliament_name;
                        $this->log("Name of the parliament is '{$parliament_name}'");
                        return $parliament_name;
                    }
                }
            }
            throw new CiviCRM_API3_Exception("Couldn't identify parliament name!");
        }
        return $parliament_name;
    }


    /**
     * Get the ID of the currently imported parliament (CiviCRM Organization), e.g. "Bundestag"
     *
     * @param CRM_Committees_Model_Model $model
     *
     * @return int
     */
    protected function getParliamentContactID($model = null)
    {
        static $parliament_id = null;
        if (!$parliament_id) {
            $parliament_name = $this->getParliamentName($model);
            $parliament = civicrm_api3('Contact', 'get', [
                'contact_type' => 'Organization',
                'contact_sub_type' => $this->getCommitteeSubType(),
                'organization_name' => $parliament_name,
                'option.limit' => 1,
            ]);
            if (empty($parliament['id'])) {
                $parliament = civicrm_api3('Contact', 'create', [
                    'contact_type' => 'Organization',
                    'contact_sub_type' => $this->getParliamentSubType(),
                    'organization_name' => $parliament_name,
                ]);
                $this->log("Created new organisation '{$parliament_name}' as it wasn't found.");
            }
            $parliament_id = $parliament['id'];
        }
        return $parliament_id;
    }


    /**
     * Get the ID of the address of the "Bundestag"
     *
     * @param CRM_Committees_Model_Model $model
     *
     * @return int
     */
    protected function getParliamentAddressID($model)
    {
        static $parliament_address_id = null;
        if ($parliament_address_id === null) {
            // get or create parliament
            $parliament_name = $this->getParliamentName($model);
            $parliament_id = $this->getParliamentContactID($model);
            // get or create the address
            $addresses = civicrm_api3('Address', 'get', [
                'contact_id' => $parliament_id,
                'location_type_id' => 'Work',
                'is_primary' => 1,
            ]);
            if (empty($addresses['id'])) {
                $parliament_address_data['location_type_id'] = 'Work';
                $parliament_address_data['is_primary'] = 1;
                $parliament_address_data['contact_id'] = $parliament_id;
                unset($parliament_address_data['organization_name']);
                unset($parliament_address_data['supplemental_address_1']);
                unset($parliament_address_data['supplemental_address_2']);
                unset($parliament_address_data['location_type']);
                $addresses = civicrm_api3('Address', 'create', $parliament_address_data);
                $this->log("Added new address to '{$parliament_name}'.");
            }
            $parliament_address_id = $addresses['id'];
        }

        return $parliament_address_id;
    }

    /**
     * Get the civicrm location type for the give kuerschner address type
     *
     * @param string $kuerschner_location_type
     *   should be one of 'Bundestag' / 'Regierung' / 'Wahlkreis'
     *
     * @return string|null
     *   return the location type name or ID, or null/empty to NOT import
     */
    protected function getAddressLocationType($kuerschner_location_type)
    {
        switch ($kuerschner_location_type) {
            case CRM_Committees_Implementation_KuerschnerCsvImporter::LOCATION_TYPE_BUNDESTAG:
                return 'Work';

            default:
            case CRM_Committees_Implementation_KuerschnerCsvImporter::LOCATION_TYPE_REGIERUNG:
            case CRM_Committees_Implementation_KuerschnerCsvImporter::LOCATION_TYPE_WAHLKREIS:
                // don't import
                return null;
        }
    }

    /**
     * Get the organization subtype for committees
     *
     * @return string
     *   the subtype name or null/empty string
     */
    protected function getCommitteeSubType()
    {
        static $was_created = false;
        if (!$was_created) {
            $this->createContactTypeIfNotExists(self::COMMITTE_SUBTYPE_NAME, self::COMMITTE_SUBTYPE_LABEL, 'Organization');
            $was_created = true;
        }
        return self::COMMITTE_SUBTYPE_NAME;
    }

    /**
     * Get the organization subtype for the parliament
     *
     * @return string
     *   the subtype name or null/empty string
     */
    protected function getParliamentSubType()
    {
        return null;
    }

    /**
     * Get the relationship type ID to be used for the given membership
     *
     * @param CRM_Committees_Model_Membership $membership
     *   the membership
     *
     * @return integer
     *   relationship type ID
     */
    protected function getRelationshipTypeIdForMembership($membership) : int
    {
        $role = $membership->getAttribute('role');
        $role2relationship_type = $this->getRoleToRelationshipTypeIdMapping();
        if (isset($role2relationship_type[$role])) {
            return $role2relationship_type[$role];
        } else {
            if ($role) {
                $this->log("Warning: Couldn't map role '{$role}' to relationship type! Using member...");
            }
            return $role2relationship_type['Mitglied'];
        }
    }

    /**
     * Get the mapping of the role name to the relationship type id
     *
     * @return array
     *   role name to relationship type ID
     */
    protected function getRoleToRelationshipTypeIdMapping() : array
    {
        static $role2relationship_type = null;
        if ($role2relationship_type === null) {
            $role2relationship_type = [];

            // create relationship types
            $chairperson_relationship = $this->createRelationshipTypeIfNotExists(
                'is_committee_chairperson_of',
                'has_committee_chairperson',
                "Vorsitzende*r von",
                "Vorsitzende*r ist",
                'Individual',
                'Organization',
                null,
                $this->getCommitteeSubType(),
                ""
            );

            $deputy_chairperson_relationship = $this->createRelationshipTypeIfNotExists(
                'is_committee_deputy_chairperson_of',
                'has_committee_deputy_chairperson',
                "stellv. Vorsitzende*r von",
                "stellv. Vorsitzende*r ist",
                'Individual',
                'Organization',
                null,
                $this->getCommitteeSubType(),
                ""
            );

            $obperson_relationship = $this->createRelationshipTypeIfNotExists(
                'is_committee_obperson_of',
                'has_committee_obperson',
                "Obperson von",
                "Obperson ist",
                'Individual',
                'Organization',
                null,
                $this->getCommitteeSubType(),
                ""
            );

            $member_relationship = $this->createRelationshipTypeIfNotExists(
                'is_committee_member_of',
                'has_committee_member',
                "Mitglied von",
                "Mitglied ist",
                'Individual',
                'Organization',
                null,
                $this->getCommitteeSubType(),
                ""
            );

            $deputy_member_relationship = $this->createRelationshipTypeIfNotExists(
                'is_committee_deputy_member_of',
                'has_committee_deputy_member',
                "stellv. Mitglied von",
                "stellv. Mitglied ist",
                'Individual',
                'Organization',
                null,
                $this->getCommitteeSubType(),
                ""
            );

            // compile role mapping:
            $role2relationship_type['stellv. Mitglied'] = $deputy_member_relationship['id'];
            $role2relationship_type['Mitglied'] = $member_relationship['id'];
            $role2relationship_type['stellv. Mitglied'] = $member_relationship['id'];
            $role2relationship_type['Obmann'] = $obperson_relationship['id'];
            $role2relationship_type['Obfrau'] = $obperson_relationship['id'];
            $role2relationship_type['Obperson'] = $obperson_relationship['id'];
            $role2relationship_type['Vorsitzender'] = $chairperson_relationship['id'];
            $role2relationship_type['Vorsitzende'] = $chairperson_relationship['id'];
            $role2relationship_type['stellv. Vorsitzender'] = $deputy_chairperson_relationship['id'];
            $role2relationship_type['stellv. Vorsitzende'] = $deputy_chairperson_relationship['id'];
        }
        return $role2relationship_type;
    }

    /**
     * Generate a name for the Fraktion based on the party
     *
     * @param $party_name
     *
     * @return string
     */
    public function getFraktionName($party_name)
    {
        return E::ts("Fraktion %1 im Deutschen Bundestag", [1 => $party_name]);
    }
}
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
    const CONTACT_SOURCE = 'kuerschners_MdB_2022';

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
        $this->createRelationshipTypeIfNotExists(
            'is_committee_member_of',
            'committee_has_member',
            "Mitglied",
            'Mitglied',
            'Individual',
            'Organization',
            null,
            null,
            ""
        );

        /**************************************
         **        RUN SYNCHRONISATION       **
         **************************************/

        // SYNC COMMITEES
        $committee_id_to_contact_id = [];
        $committees = $model->getAllCommittees();
        $this->log("Syncing " . count($committees) . " committees...");
        foreach ($model->getAllCommittees() as $committee) {
            $committee_name = $committee->getAttribute('name');
            if ($committee->getAttribute('type') == self::COMMITTEE_TYPE_PARLIAMENTARY_GROUP) {
                $committee_name = E::ts("Fraktion %1 im Deutschen Bundestag", [1 => $committee_name]);
            }
            // find contact
            $search_result = $this->callApi3('Contact', 'get', [
                'organization_name' => $committee_name,
                'contact_type' => 'Organization'
            ]);
            if (!empty($search_result['id'])) {
                // single hit
                $this->log("Committee '{$committee_name}' found: " . $search_result['id']);
                $committee_id_to_contact_id[$committee->getID()] = $search_result['id'];

            } else if (count($search_result['values']) > 1) {
                // multiple hits
                $first_committee = reset($search_result['values']);
                $committee_id_to_contact_id[$committee->getID()] = $first_committee['id'];
                $this->log("Committee '{$committee_name}' not unique! Using ID [{$first_committee['id']}]", 'warn');

            } else {
                // doesn't exist -> create
                $create_result = $this->callApi3('Contact', 'create', [
                    'organization_name' => $committee_name,
                    'contact_type' => 'Organization'
                ]);
                $committee_id_to_contact_id[$committee->getID()] = $create_result['id'];
                $this->log("Committee '{$committee_name}' created: ID [{$create_result['id']}");
            }
        }

        // SYNC CONTACTS
        $this->log("Syncing " . count($model->getAllPersons()) . " individuals...");
        $person_id_2_civicrm_id = [];
        $person_update_count = 0;
        $gender_map = ['m' => 2, 'w' => '1']; // todo: config? detect?
        $prefix_map = ['Herr' => 3, 'Herrn' => 3, 'Frau' => '1']; // todo: config? detect?
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
                $this->log("Contact [{$person->getID()}] found: [{$person_civicrm_id}]. Will be updated.");
                $person_data['id'] = $person_civicrm_id;
            } else {
                $this->log("Contact [{$person->getID()}] not found. Will be created.");
            }

            // prepare data for Contact.create
            $person_data['contact_type'] = $this->getContactType($person_data);
            $person_data['contact_sub_type'] = $this->getContactSubType($person_data);
            $person_data['source'] = self::CONTACT_SOURCE;
            $person_data['gender_id'] = $this->getGenderId($person_data);
            $person_data['prefix_id'] = $this->getPrefixId($person_data);
            $person_data['suffix_id'] = $this->getSuffixId($person_data);
            unset($person_data['id']);

            $result = $this->callApi3('Contact', 'create', $person_data);
            $person_civicrm_id = $result['id'];
            $this->setIDTContactID($person->getID(), $person_civicrm_id, self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX);
            $this->log("Kürschner Contact [{$person->getID()}] created with CiviCRM-ID [{$person_civicrm_id}].");

            // add addresses
            if (isset($address_by_contact[$person->getID()])) {
                foreach ($address_by_contact[$person->getID()] as $address) {
                    /** @var CRM_Committees_Model_Address $address */
                    $address_data = $address->getData();
                    unset($address_data['location_type']);
                    $address_data['contact_id'] = $person_civicrm_id;
                    $address_data['is_primary'] = 1;
                    $address_data['location_type_id'] = 'Work';
                    // todo: master_id Bundestag
                    $result = $this->callApi3('Address', 'create', $address_data);
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
        $this->addContactToGroup($person_civicrm_id, $lobby_contact_group_id, true);
        $this->log("Syncing contacts complete, {$person_update_count} new contacts were created.");

        // SYNC MEMBERSHIPS
        $this->log("Syncing " . count($model->getAllMemberships()) . " committee memberships...");
        $membership_update_count = 0;
        foreach ($model->getAllMemberships() as $membership) {
            /** @var $membership \CRM_Committees_Model_Membership */
            $person_civicrm_id = $this->getIDTContactID($membership->getPerson()->getID(), self::ID_TRACKER_TYPE, self::ID_TRACKER_PREFIX);
            $committee_id = $committee_id_to_contact_id[$membership->getCommittee()->getID()];
            $relationship_type_id = $this->getRelationshipTypeID('is_committee_member_of');
            // todo: cache?
            $relationship_exists = $this->callApi3('Relationship', 'getcount', [
                'contact_id_a' => $person_civicrm_id,
                'contact_id_b' => $committee_id,
                'relationship_type_id' => $relationship_type_id,
                'is_active' => 1,
            ]);
            if (!$relationship_exists) {
                $this->callApi3('Relationship', 'create', [
                    'contact_id_a' => $person_civicrm_id,
                    'contact_id_b' => $committee_id,
                    'relationship_type_id' => $relationship_type_id,
                    'is_active' => 1,
                    'description' => $membership->getAttribute('role'),
                ]);
                $membership_update_count++;
            }
        }
        $this->log("Syncing committee memberships complete, {$membership_update_count} new memberships were created.");
    }








    /*****************************************************************************
     **                            DETAILS CUSTOMISATION                        **
     **         OVERWRITE THESE METHODS TO ADJUST TO YOUR DATA MODEL            **
     *****************************************************************************/

    /**
     * Get the right gender ID for the given person data
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

}
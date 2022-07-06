<?php
/*-------------------------------------------------------+
| SYSTOPIA Committee Framework                           |
| Copyright (C) 2022 SYSTOPIA                            |
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
 * Importer to crawl the abgeordnetenwatch.de API
 *
 * remark: currently requires some changes to the main structure as included in this branch
 * @see https://www.abgeordnetenwatch.de/api
 */
class CRM_Committees_Implementation_AbgeordnetenwatchImporter extends CRM_Committees_Plugin_Importer
{
    /**
     * Does this importer require a file?
     *
     * @return boolean
     *   true iff the importer requires a file
     */
    public function requiresFile() : bool
    {
        return false; // we address the API
    }

    protected function getApiUrl($path = '', $filter = [])
    {
        return 'https://www.abgeordnetenwatch.de/api/v2/' . $path;
    }

    /**
     * Fetch the given data from the API
     *
     * @param string $path
     *   API subpath
     *
     * @param string $filter
     *   filter parameters, see https://www.abgeordnetenwatch.de/api
     *
     * @return mixed
     */
    protected function queryApi($path = '', $filter = [])
    {
        $url = $this->getApiUrl($path, $filter);
        $raw_reply = file_get_contents($url);
        $reply = json_decode($raw_reply, true);
        return $reply;
    }

    protected function getParliamentURL()
    {
        // todo: dynamically detect the latest bundestag
        return 'parliaments/5';
    }

    protected function getParliamentCommitteeType()
    {
        return 'parliamentary_committee';
    }

    protected function getParliamentGroupType()
    {
        return 'parliamentary_group';
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
        // check if we can get something from the API
        $test = $this->queryApi($this->getParliamentURL());
        if (!empty($test)) {
            $this->log("API responds, url is " . $this->getApiUrl());
            return true;
        } else {
            return false;
        }
    }

    /**
     * Import the file
     *
     * @param string $file_path
     *   the local path to the file
     *
     * @return boolean
     *   true iff the file was successfully importer
     */
    public function importModel($file_path) : bool
    {
        $this->checkRequirements();

        // configure model to enforce lower case emails
        $this->model->setProperty(CRM_Committees_Model_Email::MODEL_PROPERTY_EMAIL_LOWER_CASE, true);





        $this->log(count($data_set) . " data sets read.");
        foreach ($data_set as $record) {
            // extract member's of parliament (MOP)
            $mop = $this->copyAttributes($record, self::CONTACT_ATTRIBUTES);
            $mop['last_name'] = trim($mop['last_name_prefix'] . ' ' . $mop['last_name']);
            unset($mop['last_name_prefix']);
            $this->model->addPerson($mop);

            /**********************************
             **       PARLIAMENT SECTION     **
             **********************************/

            // extract PARLIAMENT address
            $address = $this->copyAttributes($record, array_keys(self::ADDRESS_PARLIAMENT_ATTRIBUTES), self::ADDRESS_PARLIAMENT_ATTRIBUTES);
            if (count(array_filter($address)) > 1) { // the contact_id is always there
                $address['location_type'] = self::LOCATION_TYPE_BUNDESTAG;
                $this->model->addAddress($address);
            }

            // extract PARLIAMENT emails
            $email = $this->copyAttributes($record, array_keys(self::EMAIL_PARLIAMENT_ATTRIBUTES), self::EMAIL_PARLIAMENT_ATTRIBUTES);
            if (!empty($email['email'])) {
                $email['location_type'] = self::LOCATION_TYPE_BUNDESTAG;
                $this->model->addEmail($email);
            }

            // extract PARLIAMENT phones
            $phone = $this->copyAttributes($record, array_keys(self::PHONE_PARLIAMENT_ATTRIBUTES), self::PHONE_PARLIAMENT_ATTRIBUTES);
            $phone['phone'] = trim($phone['phone_prefix'] . ' ' . $phone['phone']);
            $phone['phone'] = str_replace(['"', '='], '', $phone['phone']);
            if (!empty($phone['phone'])) {
                unset($phone['phone_prefix']);
                $phone['phone_numeric'] = preg_replace('/[^0-9]/', '', $phone['phone']);
                $phone['location_type'] = self::LOCATION_TYPE_BUNDESTAG;
                $this->model->addPhone($phone);
            }

            /***********************************
             **  GOVERNMENT/MINISTRY SECTION  **
             ***********************************/

            // extract MINISTRY address
            $address = $this->copyAttributes($record, array_keys(self::ADDRESS_GOVERNMENT_ATTRIBUTES), self::ADDRESS_GOVERNMENT_ATTRIBUTES);
            $supplemental_address = trim("{$address['supplemental_address_1']} {$address['supplemental_address_2']} {$address['supplemental_address_3']} {$address['supplemental_address_4']} ");
            $supplemental_address_wrapped = explode( "\n", wordwrap($supplemental_address, 96));
            $address['supplemental_address_1'] = $supplemental_address_wrapped[0];
            $address['supplemental_address_2'] = $supplemental_address_wrapped[1] ?? '';
            $address['supplemental_address_3'] = $supplemental_address_wrapped[2] ?? '';
            unset($address['supplemental_address_4']);
            if (count(array_filter($address)) > 1) { // the contact_id is always there
                $address['location_type'] = self::LOCATION_TYPE_REGIERUNG;
                $this->model->addAddress($address);
            }

            // extract MINISTRY phones
            $phone = $this->copyAttributes($record, self::PHONE_GOVERNMENT_ATTRIBUTES);
            $phone['phone'] = trim($phone['phone_prefix']. ' ' .$phone['phone']);
            $phone['phone'] = str_replace(['"', '='], '', $phone['phone']);
            if (!empty($phone['phone'])) {
                unset($phone['phone_prefix']);
                $phone['location_type'] = self::LOCATION_TYPE_REGIERUNG;
                $this->model->addPhone($phone);
            }


            /**************************************
             **  CONSTITUENCY / WAHLBÜRO SECTION **
             **************************************/

            // extract CONSTITUENCY address
            $address = $this->copyAttributes($record, array_keys(self::ADDRESS_CONSTITUENCY_ATTRIBUTES), self::ADDRESS_CONSTITUENCY_ATTRIBUTES);
            $address['supplemental_address_1'] = trim($address['supplemental_address_1']);
            if (count(array_filter($address)) > 1) { // the contact_id is always there
                $address['location_type'] = self::LOCATION_TYPE_WAHLKREIS;
                $this->model->addAddress($address);
            }

            // extract CONSTITUENCY phones
            $phone = $this->copyAttributes($record, self::PHONE_CONSTITUENCY_ATTRIBUTES);
            $phone['phone'] = trim($phone['phone_prefix']. ' ' .$phone['phone']);
            $phone['phone'] = str_replace(['"', '='], '', $phone['phone']);
            if (!empty($phone['phone'])) {
                unset($phone['phone_prefix']);
                $phone['location_type'] = self::LOCATION_TYPE_WAHLKREIS;
                $this->model->addPhone($phone);
            }
        }
        $this->log(count($this->model->getAllPersons()) . " individuals extracted.");
        $this->log(count($this->model->getAllAddresses()) . " addresses extracted.");
        $this->log(count($this->model->getAllPhones()) . " phone numbers extracted.");


        /**************************************
         ** COMMITTEES: Ausschüsse           **
         **************************************/

        // import "AUSSCHüSSE"
        // extract committees: first collect all entries
        $committee_list = [];
        foreach ($data_set as $record) {
            // extract member's of parliament
            if (!empty($record['committees'])) {
                $committees = $this->unpackCommittees($record['committees']);
                foreach ($committees as [$committee_name, $member_role]) {
                    if (!in_array($committee_name, $committee_list)) {
                        $committee_list[] = $committee_name;
                    }
                }
            }
        }
        // ...then add all committees to the data model
        foreach ($committee_list as $committee_name) {
            $this->model->addCommittee([
               'name' => $committee_name,
               'id'   => self::getCommitteeID($committee_name),
               'type' => self::COMMITTEE_TYPE_PARLIAMENTARY_COMMITTEE,
           ]);
        }

        // extract committee memberships
        foreach ($data_set as $record) {
            if (!empty($record['committees'])) {
                $committees = $this->unpackCommittees($record['committees']);
                foreach ($committees as [$committee_name, $member_role]) {
                    $this->model->addCommitteeMembership(
                        [
                            'contact_id' => $record['id'],
                            'committee_id' => self::getCommitteeID($committee_name),
                            'committee_name' => $committee_name,
                            'type' => self::COMMITTEE_TYPE_PARLIAMENTARY_COMMITTEE,
                            'role' => $member_role,
                        ]
                    );
                }
            }
        }


        /**************************************
         ** COMMITTEES: Fraktionen           **
         **************************************/

        // import "FRAKTIONEN"
        $parliamentary_groups_list = [];
        foreach ($data_set as $record) {
            $parliamentary_group_value = trim($record['Fraktion']);
            if (!empty($parliamentary_group_value)) {
                $parliamentary_group_name = $record['Fraktion'];
            } else {
                $parliamentary_group_name = E::ts('no parliamentary group');
            }
            if (!in_array($parliamentary_group_name, $parliamentary_groups_list)) {
                $parliamentary_groups_list[] = $parliamentary_group_name;
            }
        }
        // ...then add all committees to the data model
        foreach ($parliamentary_groups_list as $parliamentary_group_name) {
            $this->model->addCommittee([
               'name' => $parliamentary_group_name,
               'type' => self::COMMITTEE_TYPE_PARLIAMENTARY_GROUP,
               'id'   => self::getCommitteeID($parliamentary_group_name),
           ]);
        }
        // extract committee memberships
        foreach ($data_set as $record) {
            if (!empty($record['Fraktion'])) {
                $parliamentary_group_name = trim($record['Fraktion']);
                $this->model->addCommitteeMembership(
                    [
                        'contact_id' => $record['id'],
                        'committee_id' => self::getCommitteeID($parliamentary_group_name),
                        'committee_name' => $parliamentary_group_name,
                        'type' => self::COMMITTEE_TYPE_PARLIAMENTARY_GROUP,
                        'role' => 'Mitglied',
                    ]
                );
            }
        }
        $this->log(count($this->model->getAllCommittees()) . " committees extracted.");

        $this->log("If you're using this free module, send some grateful thoughts to OXFAM Germany.");
        return true;
    }

    /**
     * Generate a unique ID from the committee name
     *
     * @param string $committee_name
     *
     * @return string ID
     */
    public static function getCommitteeID($committee_name)
    {
        return substr(sha1($committee_name), 0, 8);
    }

    /**
     * Generate a unique ID from the committe name
     *
     * @param string $packed_committee_string
     *
     * @return array
     *    committee_name => member function
     */
    protected function unpackCommittees($packed_committee_string)
    {
        $committee2function = [];
        $entries = explode('),', $packed_committee_string);
        foreach ($entries as $entry) {
            //if (preg_match('/^([a-zA-ZäöüÄÖÜß ,]+) \(([a-zA-ZäöüÄÖÜß \.]+)$/', $entry, $match)) {
            if (preg_match('/^([^\(]+) \(([^\(]+)$/', $entry, $match)) {
                $committee2function[] = [trim($match[1]), trim($match[2], " \t\n\r\0\x0B)")];
            }
        }
        return $committee2function;
    }
}
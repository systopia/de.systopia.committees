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
 * Importer for Session XLS Export
 *
 * @todo migrate to separate extension
 */
class CRM_Committees_Implementation_KuerschnerCsvImporter extends CRM_Committees_Plugin_Importer
{
    const CSV_MAPPING = [
        'LFDNR' => 'id',
        'TITEL' => 'formal_title',
        'NAMENSZEILE' => 'NOT USED',
        'NACHNAME' => 'last_name',
        'VORNAME' => 'first_name',
        'PRAEFIX' => 'last_name_prefix',
        'GESCHLECHT' => 'gender_id',
        'ANREDEHERRNFRAU' => 'prefix_id',
        'FRAKTION' => 'Fraktion',
        'GREMIEN' => 'committees',
        'EMAIL1' => 'email',
        'ADRESSEPARL' => 'NOT USED',
        'ADRESSZUSATZPARL' => 'NOT USED',
        'STRASSEPOSTFACHPARL' => 'NOT USED',
        'PLZPARL' => 'NOT USED',
        'ORTPARL' => 'NOT USED',
        'EUMITGLIEDSLANDPARL' => 'NOT USED',
        'TELEFONVORWAHLPARL' => 'parliament_phone_prefix',
        'TELEFONNUMMERPARL' => 'parliament_phone',
        'MINISTERIUMAMTREG' => 'government_address_1',
        'RADRESSZUSATZ1' => 'government_address_2',
        'RADRESSZUSATZ2' => 'government_address_3',
        'RADRESSZUSATZ3' => 'government_address_4',
        'STRASSEPOSTFACHREG' => 'government_street_address',
        'PLZREG' => 'government_postal_code',
        'ORTREG' => 'government_city',
        'EUMITGLIEDSLANDREG' => 'NOT USED',
        'TELEFONVORWAHLREG' => 'government_phone_prefix',
        'TELEFONNUMMERREG' => 'government_phone',
        'WAHLKREIS' => 'constituency_type',
        'ADRESSZUSATZWK' => 'constituency_supplemental_address_1',
        'STRASSEPOSTFACHWK' => 'constituency_street_address',
        'PLZWK' => 'constituency_postal_code',
        'ORTWK' => 'constituency_city',
        'EUMITGLIEDSLANDWK' => 'NOT USED',
        'TELEFONVORWAHLWK' => 'constituency_phone_prefix',
        'TELEFONNUMMERWK' => 'constituency_phone',
    ];

    // todo: import bundestag

    // location types
    const LOCATION_TYPE_BUNDESTAG = 'Bundestag'; // parliament
    const LOCATION_TYPE_REGIERUNG = 'Regierung'; // government
    const LOCATION_TYPE_WAHLKREIS = 'Wahlkreis'; // constituency

    const CONTACT_ATTRIBUTES = ['id', 'formal_title', 'gender_id', 'first_name', 'last_name', 'last_name_prefix', 'prefix_id'];
    const PHONE_PARLIAMENT_ATTRIBUTES = ['id' => 'contact_id', 'parliament_phone_prefix' => 'phone_prefix', 'parliament_phone' => 'phone'];
    const EMAIL_PARLIAMENT_ATTRIBUTES = ['id' => 'contact_id', 'email' => 'email'];
    const ADDRESS_GOVERNMENT_ATTRIBUTES = ['id' => 'contact_id', 'government_street_address' => 'street_address', 'government_postal_code' => 'postal_code', 'government_city' => 'city', 'government_address_1' => 'supplemental_address_1', 'government_address_2' => 'supplemental_address_2', 'government_address_3' => 'supplemental_addr4ss_3', 'government_address_4' => 'supplemental_address_4'];
    const PHONE_GOVERNMENT_ATTRIBUTES = ['id' => 'contact_id', 'government_phone_prefix' => 'phone_prefix', 'government_phone' => 'phone'];
    const ADDRESS_CONSTITUENCY_ATTRIBUTES = ['id' => 'contact_id', 'constituency_street_address' => 'street_address', 'constituency_postal_code' => 'postal_code', 'constituency_city' => 'city', 'constituency_address_1' => 'supplemental_address_1'];
    const PHONE_CONSTITUENCY_ATTRIBUTES = ['id' => 'contact_id', 'constituency_phone_prefix' => 'phone_prefix', 'constituency_phone' => 'phone'];


    /** @var array our sheets extracted from the file */
    private $raw_data = null;

    /**
     * This function will be called *before* the plugin will do it's work.
     *
     * If your implementation has any external dependencies, you should
     *  register those with the registerMissingRequirement function.
     *
     */
    public function checkRequirements()
    {
        // todo: nothing to check?
        return true;
    }

    /**
     * Probe the file an add warnings/errors
     *
     * @param string $file_path
     *   the local path to the file
     *
     * @return boolean
     *   true iff the file can be processed
     */
    public function probeFile($file_path) : bool
    {
        if ($this->checkRequirements()) {
            try {
                // open file, and look for important values
                $file_handle = fopen($file_path, 'rb');
                $data = $this->readCSV($file_handle, 'Windows-1252', ';', null, 10);
                if (empty($data)) {
                    $this->logError(E::ts("File doesn't contain data"));
                    return false;
                }
                $first_record = reset($data);
                foreach (self::CSV_MAPPING as $original_column_name => $mapped_column_name)
                {
                    if (!isset($first_record[$original_column_name])) {
                        $this->logError(E::ts("Column '{$original_column_name}' missing from input file."));
                        return false;
                    }
                }

            } catch (Exception $ex) {
                $this->logException($ex);
                return false;
            }
            return true;
        }
        return false; // requirements not met
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
        // open file, and look for important values
        $file_handle = fopen($file_path, 'rb');
        $data_set = $this->readCSV($file_handle, 'Windows-1252', ';', self::CSV_MAPPING, null, true);
        foreach ($data_set as $record) {
            // extract member's of parliament (MOP)
            $mop = $this->copyAttributes($record, self::CONTACT_ATTRIBUTES);
            $mop['last_name'] = trim($mop['last_name_prefix'] . ' ' . $mop['last_name']);
            unset($mop['last_name_prefix']);
            $this->model->addPerson($mop);

            /**********************************
             **       PARLIAMENT SECTION     **
             **********************************/

            // todo: add 'Bundestag' Address? it's identical for all MOPs...

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
            $address['supplemental_address_2'] = trim("{$address['supplemental_address_2_1']} {$address['supplemental_address_2_2']} {$address['supplemental_address_2_3']}");
            unset($address['supplemental_address_2_1'], $address['supplemental_address_2_2'], $address['supplemental_address_2_3']);
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
                foreach ($committees as $committee_name => $member_role) {
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
               'id'   => $this->getCommitteeID($committee_name),
               'type' => 'parliamentary_committee'
           ]);
        }

        // extract committee memberships
        foreach ($data_set as $record) {
            if (!empty($record['committees'])) {
                $committees = $this->unpackCommittees($record['committees']);
                foreach ($committees as $committee_name => $member_role) {
                    $this->model->addCommitteeMembership(
                        [
                            'contact_id' => $record['id'],
                            'committee_id' => $this->getCommitteeID($committee_name),
                            'type' => 'parliamentary_committee'
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
               'type' => 'parliamentary_group',
               'id'   => $this->getCommitteeID($parliamentary_group_name),
           ]);
        }
        // extract committee memberships
        foreach ($data_set as $record) {
            if (!empty($record['Fraktion'])) {
                $parliamentary_group_name = trim($record['Fraktion']);
                $this->model->addCommitteeMembership(
                    [
                        'contact_id' => $record['id'],
                        'committee_id' => $this->getCommitteeID($parliamentary_group_name),
                    ]
                );
            }
        }

        return true;
    }

    /**
     * Read a whole row into a named array
     *
     * @param object $sheet
     *   the PhpOffice spreadsheet
     * @param integer $row_number
     *   the row number to read
     * @param array $col2field
     *   mapping of column number to field name
     *
     * @return array
     *   data set based on the $col2field mapping
     */
    protected function readRow($sheet, $row_number, $col2field)
    {
        $record = [];
        foreach ($col2field as $column_number => $field_name) {
            /** @var \PhpOffice\PhpSpreadsheet\Cell\Cell $cell */
            $cell = $sheet->getCellByColumnAndRow($column_number, $row_number, false);
            $record[$field_name] = trim($cell->getValue());
        }
        return $record;
    }

    /**
     * Generate a unique ID from the committe name
     *
     * @param string $committee_name
     *
     * @return string ID
     */
    protected function getCommitteeID($committee_name)
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
            if (preg_match('/^([a-zA-ZäöüÄÖÜß ,]+) \(([a-zA-ZäöüÄÖÜß \.]+)$/', $entry, $match)) {
                $committee2function[trim($match[1])] = trim($match[2]);
            }
        }
        return $committee2function;
    }
}
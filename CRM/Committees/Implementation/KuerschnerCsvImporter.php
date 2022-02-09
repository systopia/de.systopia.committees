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
        'MINISTERIUMAMTREG' => 'supplemental_address_1',
        'RADRESSZUSATZ1' => 'supplemental_address_2',
        'RADRESSZUSATZ2' => 'supplemental_address_3',
        'RADRESSZUSATZ3' => 'supplemental_address_4',
        'STRASSEPOSTFACHREG' => '',
        'PLZREG' => '',
        'ORTREG' => '',
        'EUMITGLIEDSLANDREG' => '',
        'TELEFONVORWAHLREG' => '',
        'TELEFONNUMMERREG' => '',
        'WAHLKREIS' => '',
        'ADRESSZUSATZWK' => 'WK_supplemental_address_1',
        'STRASSEPOSTFACHWK' => 'WK_street_address',
        'PLZWK' => 'WK_postal_code',
        'ORTWK' => 'WK_city',
        'EUMITGLIEDSLANDWK' => 'NOT USED',
        'TELEFONVORWAHLWK' => '',
        'TELEFONNUMMERWK' => '',
    ];

    // todo: import bundestag

    const CONTACT_ATTRIBUTES = ['id', 'formal_title', 'gender_id', 'first_name', 'last_name', 'last_name_prefix', 'prefix_id'];
    const PHONE_PARLIAMENT_ATTRIBUTES = ['id' => 'contact_id', 'parliament_phone_prefix' => 'phone_prefix', 'parliament_phone' => 'phone'];
    const EMAIL_PARLIAMENT_ATTRIBUTES = ['id' => 'contact_id', 'email' => 'email'];

    /** @var array our sheets extracted from the file */
    private $raw_data = null;

    /**
     * Get the label of the implementation
     * @return string short label
     */
    public function getLabel() : string
    {
        return E::ts("Kürschner Liste Bundestag (CSV)");
    }

    /**
     * Get a description of the implementation
     * @return string (html) text to describe what this implementation does
     */
    public function getDescription() : string
    {
        return E::ts("Importiert Kürschner Liste Bundestag (CSV)");
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
        $data_set = $this->readCSV($file_handle, 'Windows-1252', ';', self::CSV_MAPPING);
        foreach ($data_set as $record) {
            // extract member's of parliament
            $mop = $this->copyAttributes($record, self::CONTACT_ATTRIBUTES);
            $mop['last_name'] = trim($mop['last_name_prefix'] . ' ' . $mop['last_name']);
            unset($mop['last_name_prefix']);
            $this->model->addPerson($mop);

            // extract addresses
            $mop_address = $this->copyAttributes($record, self::ADDRESS_ATTRIBUTES, self::ADDRESS_MAPPING);


            // todo:

            // extract PARLIAMENT emails
            $email = $this->copyAttributes($record, self::EMAIL_PARLIAMENT_ATTRIBUTES);
            if (!empty($email['email'])) {
                $email['location_type'] = 'parliament';
                $this->model->addEmail($email);
            }

            // extract PARLIAMENT emails
            // todo:

            // extract PARLIAMENT phones
            $phone = $this->copyAttributes($record, self::PHONE_PARLIAMENT_ATTRIBUTES);
            $phone['phone'] = trim($phone['phone_prefix']. ' ' .$phone['phone']);
            if (!empty($phone['phone'])) {
                unset($phone['phone_prefix']);
                $phone['location_type'] = 'parliament';
                $this->model->addPhone($phone);
            }

            // todo:
        }


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
               'id'   => $this->getCommitteeID($committee_name)
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
                            'title' => $member_role
                        ]
                    );
                }
            }
        }

        // import "FRAKTIONEN"
        // extract committees: first collect all entries
        $parlamentary_groups_list = [];
        foreach ($data_set as $record) {
            if (!empty($record['Fraktion'])) {
                $parlamentary_group_name = trim($record['Fraktion']);
                if (!in_array($parlamentary_group_name, $parlamentary_groups_list)) {
                    $parlamentary_groups_list[] = $parlamentary_group_name;
                }
            }
        }
        // ...then add all committees to the data model
        foreach ($parlamentary_groups_list as $parlamentary_group_name) {
            $this->model->addCommittee([
               'name' => $parlamentary_group_name,
               'id'   => $this->getCommitteeID($parlamentary_group_name)
           ]);
        }
        // extract committee memberships
        foreach ($data_set as $record) {
            if (!empty($record['Fraktion'])) {
                $parlamentary_group_name = trim($record['Fraktion']);
                $this->model->addCommitteeMembership(
                    [
                        'contact_id' => $record['id'],
                        'committee_id' => $this->getCommitteeID($parlamentary_group_name),
                        'type' => 'parlamentary_group'
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
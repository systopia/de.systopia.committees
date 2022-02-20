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
 * @todo migrate to separate extension or leave as example?
 */
class CRM_Committees_Implementation_SessionImporter extends CRM_Committees_Plugin_Importer
{
    // the known sheets
    const SHEET_GREMIEN  = 'Session_Gremien';
    const SHEET_PERSONEN = 'Session_Personen';
    const SHEET_DETAILS  = 'Session_PersAdressen';
    const SHEET_MEMBERS  = 'Session_GrMitgl';

    const REQUIRED_SHEETS = [
        self::SHEET_PERSONEN,
        self::SHEET_GREMIEN,
        self::SHEET_DETAILS,
        self::SHEET_MEMBERS,
    ];

    const ROW_MAPPING_PERSON = [
        1 => 'id',
        2 => 'prefix',
        3 => 'last_name',
        4 => 'first_name',
        5 => 'formal_title',
    ];

    const ROW_MAPPING_GREMIUM = [
        1 => 'id',
        2 => 'handle',
        3 => 'name_short',
        4 => 'name',
        5 => 'start_date',
        6 => 'end_date',
    ];

    const ROW_MAPPING_ADDRESS = [
        1 => 'contact_id',
        2 => 'id',
        3 => 'supplemental_address_1',  // ADRNAME3
        4 => 'street_address',
        5 => 'house_number',
        6 => 'postal_code',
        7 => 'city',
    ];

    const ROW_MAPPING_EMAIL = [
        1 => 'contact_id',
        2 => 'id',
        10 => 'email',
    ];

    const ROW_MAPPING_PHONE = [
        1 => 'contact_id',
        2 => 'id',
        8 => 'phone',
    ];

    const ROW_MAPPING_MEMBERS = [
        1 => 'contact_id',
        2 => 'committee_id',
        3 => 'title',
        4 => 'represents',
        5 => 'start_date',
        6 => 'end_date',
    ];

    /** @var array our sheets extracted from the file */
    private $our_sheets = null;

    /**
     * This function will be called *before* the plugin will do it's work.
     *
     * If your implementation has any external dependencies, you should
     *  register those with the registerMissingRequirement function.
     *
     */
    public function checkRequirements()
    {
        // Check for PhpSpreadsheet library:
        // first, see if PhpSpreadsheet is already there
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            // try composer autoload
            $autoload_file = E::path('vendor/autoload.php');
            if (file_exists($autoload_file)) {
                require_once($autoload_file);
            }
        }
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            $this->registerMissingRequirement(
                'PhpSpreadsheet',
                E::ts("PhpSpreadsheet library missing."),
                E::ts("Please add the 'phpoffice/phpspreadsheet' library to composer or the code path.")
            );
            return false;
        }
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
                $our_sheets = $this->getRequiredSheets($file_path);
                $our_sheet_names = array_keys($our_sheets);
                $required_sheet_names = array_keys(self::REQUIRED_SHEETS);
                if (count($our_sheet_names) < count($required_sheet_names)) {
                    // there's some missing
                    $missing_sheet_names = array_diff($required_sheet_names, $our_sheet_names);
                    foreach ($missing_sheet_names as $missing_sheet) {
                        $this->logError(E::ts("Sheet '%1' missing.", [1 => $missing_sheet]));
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
     * Get a list of sheets that are needed
     *
     * @param string $file_path
     *   path to the xlsx file
     */
    protected function getRequiredSheets($file_path)
    {
        if ($this->our_sheets === null) {
            $this->log("Opening spreadsheet...");
            $this->our_sheets = [];
            $xls_reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
            $spreadsheet = $xls_reader->load($file_path);

            // check if all spreadsheets are there
            $all_sheets = $spreadsheet->getAllSheets();
            foreach (self::REQUIRED_SHEETS as $required_sheet) {
                // find sheet
                foreach ($all_sheets as $sheet) {
                    if ($sheet->getTitle() == $required_sheet) {
                        $this->our_sheets[$required_sheet] = $sheet;
                        continue;
                    }
                }
            }
            $this->log("All required sheets found.");
        }
        return $this->our_sheets;
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
        $sheets = $this->getRequiredSheets($file_path);

        // read gremien
        $gremien_sheet = $sheets[self::SHEET_GREMIEN];
        $row_count = $gremien_sheet->getHighestRow();
        for ($row_nr = 2; $row_nr <= $row_count; $row_nr++) {
            $record = $this->readRow($gremien_sheet, $row_nr, self::ROW_MAPPING_GREMIUM);
            $record['start_date'] = date("Y-m-d", strtotime(jdtogregorian($record['start_date'])));
            $record['end_date'] = empty($record['end_date']) ? '' :
                date("Y-m-d", strtotime(jdtogregorian($record['end_date'])));
            $this->model->addCommittee($record);
        }
        $this->log(count($this->model->getAllCommittees()) . " committees read.");

        // read persons
        $person_sheet = $sheets[self::SHEET_PERSONEN];
        $row_count = $person_sheet->getHighestRow();
        for ($row_nr = 2; $row_nr <= $row_count; $row_nr++) {
            $record = $this->readRow($person_sheet, $row_nr, self::ROW_MAPPING_PERSON);
            $this->model->addPerson($record);
        }
        $this->log(count($this->model->getAllPersons()) . " persons read.");


        // open details sheet:
        $details_sheet = $sheets[self::SHEET_DETAILS];
        $row_count = $details_sheet->getHighestRow();

        // read addresses
        for ($row_nr = 2; $row_nr <= $row_count; $row_nr++) {
            $record = $this->readRow($details_sheet, $row_nr, self::ROW_MAPPING_ADDRESS);
            $record['street_address'] = trim($record['street_address'] . ' ' . $record['house_number']);
            $this->model->addAddress($record);
        }
        $this->log(count($this->model->getAllAddresses()) . " addresses read.");

        // read emails
        for ($row_nr = 2; $row_nr <= $row_count; $row_nr++) {
            $record = $this->readRow($details_sheet, $row_nr, self::ROW_MAPPING_EMAIL);
            if (!empty($record['email'])) {
                try {
                    $this->model->addEmail($record);
                } catch (CRM_Committees_Model_ValidationException $ex) {
                    $this->log("Email '{$record['email']}' is not a valid email address.");
                }
            }
        }
        $this->log(count($this->model->getAllEmails()) . " emails read.");

        // read phones
        for ($row_nr = 2; $row_nr <= $row_count; $row_nr++) {
            $record = $this->readRow($details_sheet, $row_nr, self::ROW_MAPPING_PHONE);
            if (!empty($record['phone'])) {
                $this->model->addPhone($record);
            }
        }
        $this->log(count($this->model->getAllPhones()) . " phones read.");

        // add memberships
        $member_sheet = $sheets[self::SHEET_MEMBERS];
        $row_count = $member_sheet->getHighestRow();
        for ($row_nr = 2; $row_nr <= $row_count; $row_nr++) {
            $record = $this->readRow($member_sheet, $row_nr, self::ROW_MAPPING_MEMBERS);
            $record['start_date'] = date("Y-m-d", strtotime(jdtogregorian($record['start_date'])));
            $record['end_date'] = empty($record['end_date']) ? '' :
                date("Y-m-d", strtotime(jdtogregorian($record['end_date'])));
            $record['id'] = "{$record['contact_id']}-{$record['committee_id']}";
            $this->model->addCommitteeMembership($record);
        }
        $this->log(count($this->model->getAllMemberships()) . " committee memberships read.");

        ### special adjustments / overrides ###

        // #1 in-house: "Wenn ein Kontakt im Feld ADRNAME3 nichts stehen hat UND als Straße "Hans-Böckler-Str*"
        //   dann soll in den Adresszusatz 1 "Evangelische Kirche im Rheinland" eingefügt werden."
        $adjustments_made = 0;
        foreach ($this->model->getAllAddresses() as $address) {
            $ADRNAME3 = $address->getAttribute('supplemental_address_1');
            $STREET   = $address->getAttribute('street_address');

            if (empty($ADRNAME3) && preg_match('/^Hans-Böckler-Str.*$/i', $STREET)) {
                $adjustments_made++;
                $address->setAttribute('supplemental_address_1', "Evangelische Kirche im Rheinland");
                $this->model->addAddress($address);
            }
        }
        $this->log("{$adjustments_made} address in-house adjustments made");

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
}
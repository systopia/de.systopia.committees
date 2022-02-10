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
class CRM_Committees_Implementation_PersonalOfficeImporter extends CRM_Committees_Plugin_Importer
{
    const ROW_MAPPING = [
        1 => 'contact_id',
        2 => 'prefix_id',
        3 => 'formal_title',
        4 => 'last_name',
        5 => 'first_name',
        //6 => 'unterdienststnr.',
        7 => 'portal_id',
        8 => 'email',
        9 => 'street_address',
        10 => 'house_number',
        11 => 'postal_code',
        12 => 'city',
        //13 => 'kk/kb-. kg-. gkg-schlüssel',
        //14 => 'kk/kb-. kg-. gkg-schlüssel',
        15 => 'committee_id', // externe org. nr
        16 => 'committee_name',
        //17 => 'versand-nr.',
        //18 => 'dienststnr._2',
        //19 => 'dienststnr.',
    ];

    /** @var array our sheets extracted from the file */
    private $main_sheet = null;

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
                $main_sheet = $this->getMainSheet($file_path);
                // todo: probe sheet? column names?
            } catch (Exception $ex) {
                $this->logException($ex);
                return false;
            }
            return true;
        }
        return false; // requirements not met
    }

    /**
     * Get the main sheet of the xls file
     *
     * @param string $file_path
     *   path to the xlsx file
     */
    protected function getMainSheet($file_path)
    {
        if ($this->main_sheet === null) {
            try {
                $xls_reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
                $spreadsheet = $xls_reader->load($file_path);
                $this->main_sheet = $spreadsheet->getSheet($spreadsheet->getFirstSheetIndex());
            } catch (\PhpOffice\PhpSpreadsheet\Exception $e) {
                $this->logError("No XLS sheet found in the file.", 'error');
            }
        }
        return $this->main_sheet;
    }

    /**
     * Extract a subset of the record
     *
     * @param array $record
     *     named data
     * @param array $attributes
     *     attributes to be extracted
     * @param array $mapping
     *     attribute mapping to be applied after the copy process
     *
     * @return array
     *     attribute subset
     */
    protected function copyAttributes($record, $attributes, $mapping = [])
    {
        $subset = [];
        foreach ($attributes as $attribute) {
            $target_attribute = $mapping[$attribute] ?? $attribute;
            $subset[$target_attribute] = $record[$attribute] ?? '';
        }
        return $subset;
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
        $this->log("Opening source file");
        $main_sheet = $this->getMainSheet($file_path);
        $row_count = $main_sheet->getHighestRow();
        $this->log("Start importing {$row_count} rows");
        for ($row_nr = 2; $row_nr <= $row_count; $row_nr++) {
            $record = $this->readRow($main_sheet, $row_nr, self::ROW_MAPPING);

            // extract contact
            $contact = $this->copyAttributes($record, ['contact_id', 'formal_title', 'last_name', 'first_name'], ['contact_id' => 'id']);
            $existing_contact = $this->model->getPerson($contact['id']);
            if (!$existing_contact) {
                // add this contact
                $this->model->addPerson($contact);

                // extract address
                $address = $this->copyAttributes($record, ['contact_id', 'street_address', 'house_number', 'postal_code', 'city']);
                $address['street_address'] = trim($address['street_address'] . ' ' . $address['house_number']);
                unset($address['house_number']);
                $address['country_id'] = 'DE';
                $this->model->addAddress($address);

                // extract email
                $email = $this->copyAttributes($record, ['contact_id', 'email'], );
                if (!empty($email['email'])) {
                    $this->model->addEmail($email);
                }
            } else {
                $this->log("Skipped duplicate contact data [{$contact['id']}].");
            }

            // extract committees and membership relationships
            // remark: start / end dates currently not provided
            $committee_data = $this->copyAttributes($record, ['committee_id', 'committee_name'],
                                           ['committee_id' => 'id', 'committee_name' => 'name']);
            if (!empty($committee_data['id']) && !empty($committee_data['name'])) {
                $existing_committee = $this->model->getCommittee($committee_data['id']);
                if (!$existing_committee) {
                    $this->model->addCommittee($committee_data);
                }
            }

            $employment = $this->copyAttributes($record, ['contact_id', 'committee_id']);
            $this->model->addCommitteeMembership($employment);
        }
        $this->log(count($this->model->getAllPersons()) . " contacts read.");

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
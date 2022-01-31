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
        1 => 'id',
        2 => 'formal_title',
        3 => 'last_name',
        4 => 'first_name',
        6 => 'portal_id',
        7 => 'email',
        8 => 'street_address',
        9 => 'house_number',
        10 => 'postal_code',
        11 => 'city',
    ];

    /** @var array our sheets extracted from the file */
    private $main_sheet = null;

    /**
     * Get the label of the implementation
     * @return string short label
     */
    public function getLabel() : string
    {
        return E::ts("Personal Office Importer (XLS)");
    }

    /**
     * Get a description of the implementation
     * @return string (html) text to describe what this implementation does
     */
    public function getDescription() : string
    {
        return E::ts("Imports a 'Personal Office' XLS export.");
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
            if (isset($mapping[$attribute])) {
                $attribute = $mapping[$attribute];
            }
            $subset[$attribute] = $record[$attribute] ?? '';
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
        $main_sheet = $this->getMainSheet($file_path);
        $row_count = $main_sheet->getHighestRow();
        for ($row_nr = 2; $row_nr <= $row_count; $row_nr++) {
            $record = $this->readRow($main_sheet, $row_nr, self::ROW_MAPPING);

            // extract contact
            $contact = $this->copyAttributes($record, ['id', 'formal_title', 'last_name', 'first_name']);
            $this->model->addPerson($contact);

            // extract address
            $address = $this->copyAttributes($record, ['id', 'street_address', 'house_number', 'postal_code', 'city'], ['id' => 'contact_id']);
            $address['street_address'] = trim($address['street_address'] . ' ' . $address['house_number']);
            unset($address['house_number']);
            $address['country_id'] = 'DE';
            $this->model->addAddress($address);

            // extract email
            $email = $this->copyAttributes($record, ['id', 'email']);
            if (!empty($email['email'])) {
                $this->model->addEmail($address);
            }

            // extract employee relationship
            // TODO
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
}
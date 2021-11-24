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
class CRM_Committees_Implementation_SessionImporter extends CRM_Committees_Plugin_Importer
{
    // the known sheets
    const SHEET_PERSONEN = 'Session_Personen';

    const REQUIRED_SHEETS = [
        self::SHEET_PERSONEN
    ];

    /**
     * Get the label of the implementation
     * @return string short label
     */
    public function getLabel() : string
    {
        return E::ts("Session Importer (XLS)");
    }

    /**
     * Get a description of the implementation
     * @return string (html) text to describe what this implementation does
     */
    public function getDescription() : string
    {
        return E::ts("Imports a 'Session' XLS export.");
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
                $this->logException($ex, 'error');
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
        $our_sheets = [];
        $xls_reader = new \PhpOffice\PhpSpreadsheet\Reader\Xlsx();
        $spreadsheet = $xls_reader->load($file_path);

        // check if all spreadsheets are there
        $all_sheets = $spreadsheet->getAllSheets();
        foreach (self::REQUIRED_SHEETS as $required_sheet) {
            // find sheet
            foreach ($all_sheets as $sheet) {
                if ($sheet->getTitle() == $required_sheet) {
                    $our_sheets[$required_sheet] = $sheet;
                    continue;
                }
            }
        }
        return $our_sheets;
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
    }
}
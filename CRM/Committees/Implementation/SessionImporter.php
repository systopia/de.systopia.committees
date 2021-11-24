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
            if (file_exists('../../../vendor/autoload.php')) {
                require_once('../../../vendor/autoload.php');
            }
        }
        if (!class_exists('\PhpOffice\PhpSpreadsheet\IOFactory')) {
            $this->registerMissingRequirement(
                'PhpSpreadsheet',
                E::ts("PhpSpreadsheet library missing."),
                E::ts("Please add the 'phpoffice/phpspreadsheet' library to composer or the code path.")
            );
        }
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
        try {
            $xls_reader = new \PhpOffice\PhpSpreadsheet\Reader\Xls();
            $spreadsheet = $xls_reader->load($file_path);
            // check if all spreadsheets are there
            foreach (self::REQUIRED_SHEETS as $required_sheet) {
                $sheet = $spreadsheet->getSheet('Session_Personen');
                if (!$sheet) {
                    return false;
                }
            }
        } catch (Exception $ex) {
            $this->logException($ex, 'error');
            return false;
        }
        return true;
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

    }
}
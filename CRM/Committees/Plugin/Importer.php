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

/**
 * Base for all importers. Importers are able to
 *   1) receive a file/source of a predefinied type
 *   2) verify the file/source
 *   3) import the data into the internal model
 */
abstract class CRM_Committees_Plugin_Importer extends CRM_Committees_Plugin_Base
{
    /**
     * Return a list of the available importers, represented by the implementation class name
     *
     * @return string[]
     */
    public static function getAvailableImporters()
    {
        // todo: gather this through Symfony hook
        return [
            'CRM_Committees_Implementation_SessionImporter'
        ];
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
    public abstract function probeFile($file_path) : bool;

    /**
     * Import the file
     *
     * @param string $file_path
     *   the local path to the file
     *
     * @return boolean
     *   true iff the file was successfully importer
     */
    public abstract function importModel($file_path) : bool;

}
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
use Civi\CommitteeModuleSurvey;

/**
 * Base for all importers. Importers are able to
 *   1) receive a file/source of a predefined type
 *   2) verify the file/source
 *   3) import the data into the internal model
 */
abstract class CRM_Committees_Plugin_Importer extends CRM_Committees_Plugin_Base
{
    /** @var CRM_Committees_Model_Model */
    protected $model = null;

    public function __construct()
    {
        $this->model = new CRM_Committees_Model_Model();
    }

    /**
     * Return a list of the available importers, represented by the implementation class name
     *
     * @return string[]
     */
    public static function getAvailableImporters() : array
    {
        $importer_survey = new CommitteeModuleSurvey();
        Civi::dispatcher()->dispatch(CommitteeModuleSurvey::EVENT_NAME, $importer_survey);
        return $importer_survey->getRegisteredImporterModules();
    }

    /**
     * Register the built-in importer modules
     *
     * @param CommitteeModuleSurvey $importer_survey
     *
     * @return void
     */
    public static function registerBuiltInImporters($importer_survey)
    {
        $importer_survey->registerImporterModule(
            'de.oxfam.kuerschner',
            'CRM_Committees_Implementation_OxfamSimpleImporter',
            E::ts("Kürschner/Oxfam Simple Import"),
            null, // todo
            E::ts("Kürschner Bundestag Data - Oxfam Model - one-shot")
        );
        $importer_survey->registerImporterModule(
            'de.ekir.po.importer',
            'CRM_Committees_Implementation_PersonalOfficeImporter',
            E::ts("Personal Office Importer (XLS)"),
            null,
            E::ts("Imports a 'Personal Office's XLS export.")
        );
        $importer_survey->registerImporterModule(
            'de.ekir.session.syncer',
            'CRM_Committees_Implementation_SessionImporter',
            E::ts("Session Importer (XLS)"),
            null,
            E::ts("Imports a 'Session' XLS export.")
        );
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

    /**
     * get the (imported) model
     *
     * @return CRM_Committees_Model_Model
     */
    public function getModel()
    {
        return $this->model;
    }

}
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


namespace Civi;

use \Civi\Core\Event\GenericHookEvent;

/**
 * Class CommitteeModuleSurvey
 *
 * Abstract event class to provide some basic functions
 */
class CommitteeModuleSurvey extends GenericHookEvent
{
    /** Symfony event name for the module registration */
    const EVENT_NAME = 'civi.committees.register_modules';

    /** @var array list of importer module metadata */
    protected $importer_modules = [];

    /** @var array list of syncer module metadata */
    protected $syncer_modules = [];

    /**
     * Register a new importer module with the system
     *
     * @param string $module_key
     *   the unique module key. If it is already registered, the previous registration will be overwritten
     *
     * @param string $module_class
     *   the module's implementation class. A subclass of CRM_Committees_Plugin_Importer
     *
     * @param string $display_name
     *   the module's name as presented to the user (optional)
     *
     * @param string $config_link
     *   url of the module's configuration page (optional)
     *
     * @param string $help_text
     *   html help text to be presented to the user
     *
     * @return void
     */
    public function registerImporterModule($module_key, $module_class, $display_name = null, $config_link = null, $help_text = null, $params = null)
    {
        $this->importer_modules[$module_key] = [
            'key' => $module_key,
            'class' => $module_class,
            'display_name' => $display_name,
            'config_link' => $config_link,
            'help_text' => $help_text,
            'params' => $params,
        ];
    }

    /**
     * Get the list of all currently registered import modules
     *
     * @return array
     *    a list of arrays with the modules' metadata
     */
    public function getRegisteredImporterModules()
    {
        return $this->importer_modules;
    }

    /**
     * Get the importer module instance represented by the key
     *
     * @param string $key
     *   the module key
     *
     * @return
     *    module specs
     */
    public function getImportModule($key)
    {
        if (isset($this->importer_modules[$key])) {
            $specs = $this->importer_modules[$key];
            return new $specs['class']($specs['config']);
        }
        return null;
    }

    /**
     * Register a new syncer module with the system
     *
     * @param string $module_key
     *   the unique module key. If it is already registered, the previous registration will be overwritten
     *
     * @param string $module_class
     *   the module's implementation class. A subclass of CRM_Committees_Plugin_Syncer
     *
     * @param string $display_name
     *   the module's name as presented to the user (optional)
     *
     * @param string $config_link
     *   url of the module's configuration page (optional)
     *
     * @param string $help_text
     *   html help text to be presented to the user
     *
     * @return void
     */
    public function registerSyncerModule($module_key, $module_class, $display_name = null, $config_link = null, $help_text = null, $params = null)
    {
        $this->syncer_modules[$module_key] = [
            'key' => $module_key,
            'class' => $module_class,
            'display_name' => $display_name,
            'config_link' => $config_link,
            'help_text' => $help_text,
            'params' => $params
        ];
    }

    /**
     * Get the list of all currently registered import modules
     *
     * @return array
     *    a list of arrays with the modules' metadata
     */
    public function getRegisteredSyncerModules()
    {
        return $this->syncer_modules;
    }
}

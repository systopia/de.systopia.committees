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
 * Base entity for plugins, i.e. Importer and Syncer
 */
abstract class CRM_Committees_Plugin_Base
{
    /** @var array module config represented by the */
    protected $_module_config;

    /** @var array data structure for the missing requirements */
    protected $missing_requirements = [];

    /** @var array data structure for errors */
    protected $errors = [];

    /** @var string short module name, see getModuleName */
    protected $_module_name = null;

    /** @var resource the current logger, see getLogResource()  */
    static private $progress_logger = null;

    /** @var string $current_log_file (full path) */
    protected $current_log_file = null;


    /**
     * Create a new instance of this module with the given config
     *
     * @param $module_config
     */
    public function __construct($module_config)
    {
        $this->_module_config = $module_config;
    }

    /**
     * This function will be called to check whether the requirements are met
     *
     * If your implementation has any external dependencies, you should
     *  register those with the registerMissingRequirement function.
     *
     */
    public function checkRequirements()
    {
        // no requirements for the base class
    }

    /**
     * Check whether the given extension is active
     *
     * @param string $extension_key
     *   the full extension key
     */
    public function extensionAvailable($extension_key)
    {
        static $extensions = null;
        if ($extensions === null) {
            $extensions = civicrm_api3('Extension', 'get', ['option.limit' => 0])['values'];
        }
        foreach ($extensions as $extension) {
            if ($extension['key'] == $extension_key) {
                return true;
            }
        }
        return false;
    }

    /**
     * Will register a requirement issue
     *
     * @param string $issue_id
     *   internal identifier string, mostly used to avoid duplicates
     *
     * @param string $issue_label
     *   localised issue label
     * @param string $issue_description
     *   localised issue description (html), ideally with pointers to how to fix it
     */
    protected function registerMissingRequirement($issue_id, $issue_label, $issue_description)
    {
        $this->missing_requirements[$issue_id] = [
            'id' => $issue_id,
            'label' => $issue_label,
            'description' => $issue_description,
        ];
    }

    /**
     * get the list of missing requirements
     *
     * @return array list of missing requirement data
     */
    public function getMissingRequirements()
    {
        return $this->missing_requirements;
    }

    /**
     * Will register an error with a given requirement
     *
     * @param \Exception $ex
     *   localised issue label
     * @param string $description
     *   localised issue description (html), ideally with pointers to how to fix it
     */
    public function logException($ex, $description = null, $level = 'error')
    {
        $this->logError(
            E::ts("%1 exception", [1 => get_class($ex)]),
                $level,
                $description ?? $ex->getMessage()
            );
    }

    /**
     * Will register an error with a given requirement.
     * The message will also be posted to the log file
     *
     * @param string $label
     *   localised issue label
     *
     * @param string $description
     *   localised issue description (html), ideally with pointers to how to fix it
     *
     * @param string $level
     *   one of
     */
    public function logError($label, $level = 'error', $description = '')
    {
        // store message
        $this->errors[] = [
            'level' => $level,
            'label' => $label,
            'description' => $description,
        ];

        // also log it
        if ($description) {
            $this->log($label . ':' . $description, $level);
        } else {
            $this->log($label, $level);
        }
    }


    /**
     * Get a short name for this module
     *
     * @return string
     */
    protected function getModuleName()
    {
        if ($this->_module_name === null) {
            $class_name_tokens = explode('_', get_class($this));
            $this->_module_name = end($class_name_tokens);
        }
        return $this->_module_name;
    }

    /**
     * Log a general message to the process log file
     *
     * @param string $message
     *   log message
     * @param string $level
     *
     */
    public function log($message, $level = 'info')
    {
        // log to CiviCRM log (todo: switch off?)
        $module_name = $this->getModuleName();
        $message = date('[H:i:s]') . "[$module_name]: $message";
        switch ($level) {
            default:
            case 'debug':
            case 'info':
            case 'warning':
                $this->log2file($message, $level);
                break;

            case 'error':
                $this->log2file($message, $level);
                Civi::log()->error($message);
                break;
        }
    }


    /**
     * Get a list of errors, filtered by the given level
     *
     * @param string $level error level
     *
     * @return array list of arrays
     */
    public function getErrors($level = 'error')
    {
        $error_list = [];
        foreach ($this->errors as $error) {
            // todo: check if level is above threshold
            $error_list[] = $error;
        }
        return $error_list;
    }

    /**
     * Get the current process log file
     *
     * @return resource
     *   the logger file
     */
    public function getLogResource()
    {
        if (self::$progress_logger === null) {
            $log_folder = Civi::paths()->getPath('[civicrm.files]/ConfigAndLog');
            $this->current_log_file = $log_folder . DIRECTORY_SEPARATOR . 'Committees.' . date('Y-m-d_H:i:s') . '.log';
            self::$progress_logger = fopen($this->current_log_file, 'w');
            Civi::log()->debug("Committee importer started, log file is '{$this->current_log_file}");
        }
        return self::$progress_logger;
    }

    /**
     * Return the current log file, full path
     *
     * @return string|null
     */
    public function getCurrentLogFile()
    {
        return $this->current_log_file;
    }

    /**
     * Log the progress of this sync/import
     *
     * @param string $message
     *    the message to logW
     *
     * @return void
     */
    public function log2file($message)
    {
        $logger = $this->getLogResource();
        fputs($logger, $message);
        fputs($logger, "\n");
    }

    /**
     * Call the CiviCRM APIv3
     *
     * @param string $entity
     * @param string $action
     * @param array $params
     *
     * @return array result
     */
    public function callApi3($entity, $action, $params = [])
    {
        try {
            // do some sanity checks
            if (strtolower($action) == 'get') {
                // check if limit is disabled
                if (!isset($params['option.limit']) && !isset($params['options']['limit'])) {
                    $this->log("APIv3 {$entity}.get call has implicit limit: " . json_encode($params), 'warn');
                }
            }
            return civicrm_api3($entity, $action, $params);
        } catch (CiviCRM_API3_Exception $ex) {
            $this->logException($ex);
            return civicrm_api3_create_error("Error: " . $ex->getMessage());
        }
    }
}
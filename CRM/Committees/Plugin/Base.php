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
    /** @var array data structure for the missing requirements */
    protected $missing_requirements = [];

    /** @var array data structure for errors */
    protected $errors = [];

    /** @var resource  */
    protected $progress_logger = null;

    /**
     * Get the label of the implementation
     * @return string short label
     */
    public abstract function getLabel() : string;

    /**
     * Get a description of the implementation
     * @return string (html) text to describe what this implementation does
     */
    public abstract function getDescription() : string;

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
        $this->log($label, $level);
    }


    /**
     * Log a general message to the process log file
     *
     * @param string $message
     *   log message
     * @param string $level
     *
     *
     *
     * @return void
     */
    public function log($message, $level = 'info')
    {
        // log to CiviCRM log (todo: switch off?)
        $message = date('[H:i:s]') . ' ' . $message;
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
        if (!$this->progress_logger) {
            $log_folder = Civi::paths()->getPath('[civicrm.files]/ConfigAndLog');
            $class_name_tokens = explode('_', get_class($this));
            $module_name = end($class_name_tokens);
            $log_file = $log_folder . DIRECTORY_SEPARATOR . 'Committees.' . date('Y-m-d_H:i:s_') . $module_name . '.log';
            $this->progress_logger = fopen($log_file, 'w');
            Civi::log()->debug("Committee importer started, log file is '{$log_file}");
        }
        return $this->progress_logger;
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
}
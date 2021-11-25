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
     * Will register an error with a given requirement
     *
     * @param string $label
     *   localised issue label
     * @param string $description
     *   localised issue description (html), ideally with pointers to how to fix it
     */
    public function logError($label, $level = 'info', $description = '')
    {
        // store message
        $this->errors[] = [
            'level' => $level,
            'label' => $label,
            'description' => $description,
        ];

        // log to CiviCRM log (todo: switch off?)
        switch ($level) {
            case 'debug':
                Civi::log()->debug("{$label}: {$description}");
                break;
            default:
            case 'info':
                Civi::log()->info("{$label}: {$description}");
                break;
            case 'warning':
                Civi::log()->warning("{$label}: {$description}");
                break;
            case 'error':
                Civi::log()->error("{$label}: {$description}");
                break;
        }
    }

    /**
     * @param $message_level
     * @param $threshold_level
     */
    public function shouldLog($message_level, $threshold_level) {
        // todo: implement
        return true;
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
            if ($this->shouldLog($error['level'], $level)) {
                $error_list[] = $error;
            }
        }
        return $error_list;
    }
}
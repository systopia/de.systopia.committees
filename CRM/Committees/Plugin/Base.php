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

    /** @var string the current logger key  */
    static private $progress_logger_key = null;

    /** @var string the current logger timestamp */
    static private $progress_logger_timestamp = null;

    /** @var string $current_log_file (full path) */
    protected $current_log_file = null;

    /** @var string $current_log_file (full path) */
    protected $current_log_datestring = null;

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
     * @return boolean
     *   true if there are no missing requirements
     */
    public function checkRequirements()
    {
        // check if there are any missing requirements
        return empty($this->missing_requirements);
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
            $extensions = civicrm_api3('Extension', 'get', ['status' => 'installed', 'option.limit' => 0])['values'];
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
     * Check if this system is currently run by unit tests
     *
     * @note CAREFUL this should *not* be used for any functional differences
     *
     * @return bool
     */
    public function isUnitTest()
    {
        static $is_unit_test = null;
        if ($is_unit_test === null) {
            $is_unit_test = (strpos($_SERVER['argv'][0] ?? '', 'phpunit') !== FALSE);
        }
        return $is_unit_test;
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
                if ($this->isUnitTest()) {
                    // also log to console during unit tests
                    print_r($message . "\n");
                }
                break;

            case 'error':
                $this->log2file($message, $level);
                Civi::log()->error($message);
                if ($this->isUnitTest()) {
                    // also log to console during unit tests
                    print_r("ERROR: " . $message . "\n");
                }
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
     * Get a list of error messages, filtered by the given level
     *
     * @param boolean $unique don't repeat the same messages
     *
     * @param string $level error level
     *
     * @return array list of strings
     */
    public function getErrorMessages($unique = false, $level = 'error')
    {
        $error_msg_list = [];
        $error_list = $this->getErrors($level);
        foreach ($error_list as $error) {
            $error_msg_list[] = $error['label'];
        }

        if ($unique) {
            $error_msg_list = array_unique($error_msg_list);
        }
        return $error_msg_list;
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
            self::$progress_logger_key = bin2hex(openssl_random_pseudo_bytes(8));
            self::$progress_logger_timestamp = date('YmdHis');
            $this->current_log_file = $log_folder . DIRECTORY_SEPARATOR . 'Committees-' . self::$progress_logger_key . '.' . self::$progress_logger_timestamp . '.log';
            self::$progress_logger = fopen($this->current_log_file, 'w');
            Civi::log()->debug("Committee importer started, log file is '{$this->current_log_file}");
        }
        return self::$progress_logger;
    }

    /**
     * Get the download link for this logfile
     *
     * @return string
     *   a file download URL
     */
    public function getDownloadLink()
    {
        if (self::$progress_logger != null && self::$progress_logger_key != null) {
            $key = self::$progress_logger_key;
            $date = self::$progress_logger_timestamp;
            return CRM_Utils_System::url('civicrm/committees/logfile', "key={$key}&date={$date}");
        } else {
            return null;
        }
    }

    /**
     * Get the name for the given log file parameters
     *
     * @param string $datestring
     *   14 digits defining the log date
     *
     * @param string $key
     *   8 hex characters acting as a protection
     *
     * @return string
     *  log file name
     *
     * @throws Exception
     */
    public static function getLogFileName($datestring, $key)
    {
        if (!preg_match('/^[0-9]{14}$/', $datestring)) {
            throw new Exception(E::ts("Illegal date string"));
        }
        if (!preg_match('/^[0-9a-z]{16}$/', $key)) {
            throw new Exception(E::ts("Illegal key"));
        }
        return 'Committees-' . $key . '.' . $datestring . '.log';
    }

    /**
     * Get the content of a logfile based on the date string
     *  AND a key (so you can't efficiently guess the file names)
     *
     * @param string $datestring
     *   14 digits defining the log date
     *
     * @param string $key
     *   8 hex characters acting as a protection
     *
     * @return string
     *  file content
     *
     * @throws Exception
     */
    public static function getLogFileContent($datestring, $key)
    {
        if (!preg_match('/^[0-9]{14}$/', $datestring)) {
            throw new Exception(E::ts("Illegal date string"));
        }
        if (!preg_match('/^[0-9a-z]{16}$/', $key)) {
            throw new Exception(E::ts("Illegal key"));
        }
        $log_folder = Civi::paths()->getPath('[civicrm.files]/ConfigAndLog');
        $filepath = $log_folder . DIRECTORY_SEPARATOR . self::getLogFileName($datestring, $key);
        if (!file_exists($filepath)) {
            throw new Exception("Log file doesn't exist (any more).");
        }
        if (!is_readable($filepath)) {
            throw new Exception("Can't access log file.");
        }
        return (string) file_get_contents($filepath);
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
     * Return the current log file, full path
     *
     * @param string $datestring
     *   14 digits defining the log date
     *
     * @return string|null
     */
    public static function getCurrentLogFileName($datestring = null)
    {
        if (!$datestring) {
            $datestring = self::$progress_logger_timestamp;
        }
        return 'Committee-' . $datestring . '.log';
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
        } catch (CRM_Core_Exception $ex) {
            $this->logException($ex);
            return civicrm_api3_create_error("Error: " . $ex->getMessage());
        }
    }

    /**
     * Obfuscate a string, e.g. for logging, by replacing the middle part with a placeholder
     *
     * @param $string string
     *   the string to be obfuscated
     *
     * @param int $keep_leading
     *   only keep the leading n characters
     *
     * @param int $keep_trailing
     *   only keep the trailing n characters
     *
     * @param string $fill
     *   character to replace the remaining characters with
     *
     * @return string
     *   obfuscated string
     */
    public function obfuscate($string, $keep_leading = 3, $keep_trailing = 3, $fill = '...')
    {
        if (strlen($string) > $keep_leading + $keep_trailing) {
            return substr($string,0, $keep_leading) . $fill . substr($string, -$keep_trailing);
        } else {
            // if it's so short, should we do something else?
            return $string;
        }
    }

    /**
     * Get an option group value based on the given label
     *
     * @param string $label
     *   the label
     *
     * @param string $option_group_name
     *   the name of the option group
     *
     * @param float $minimum_similarity
     *   value <= 1.0 to indicate how well the match should be
     *
     * @return string
     *   option value, most likely a number
     */
    public function getOptionGroupValue($option_group_name, $label, $minimum_similarity = 1.0)
    {
        // cache results
        static $similarity_cache = [];

        $label2value = CRM_Core_OptionGroup::values($option_group_name, true);

        // if found literally, return:
        if (isset($label2value[$label])) return $label2value[$label];

        $best_value = '';
        $best_score = 0.0;
        foreach ($label2value as $cmp_label => $cmp_value) {
            if (!isset($similarity_cache[$option_group_name][$cmp_label][$label])) {
                similar_text($label, $cmp_label, $percent);
                $similarity_cache[$option_group_name][$cmp_label][$label] = $percent / 100.0;
            }
            $similarity = $similarity_cache[$option_group_name][$cmp_label][$label];
            if ($similarity > $best_score) {
                $best_score = $similarity;
                $best_value = $cmp_value;
            }
        }

        $this->log("Best match for '{$label}' is {$best_value} with {$best_score}");
        if ($best_score >= $minimum_similarity) {
            return $best_value;
        } else {
            return '';
        }
    }

}
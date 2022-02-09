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
 * Base for all importers. Importers are able to
 *   1) receive a file/source of a predefinied type
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
        // todo: gather this through Symfony hook, and dynamically (i.e. use the ->getLabel())
        return [
            'CRM_Committees_Implementation_SessionImporter' => "Session Importer (XLS)",
            'CRM_Committees_Implementation_PersonalOfficeImporter' => "PersonalOffice Importer (XLS)",
            'CRM_Committees_Implementation_KuerschnerCsvImporter' => "Kürschner Liste Bundestag (CSV)",
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

    /**
     * get the (imported) model
     *
     * @return CRM_Committees_Model_Model
     */
    public function getModel()
    {
        return $this->model;
    }

    /**
     * Read CSV file data
     *
     * @param resource $input_stream
     *   the data stream. will be closed by the operation
     *
     * @param string $separator
     *   column separator
     *
     * @param string $encoding
     *   string encoding
     *
     * @param array $column_mapping
     *   map column names, missing entries will be stripped
     *
     * @param int $cap
     *   stop reading after $cap entries
     *
     * @param array $headers
     *   if the file doesn't have a header, you can provide a list of header strings here
     *   In this case, the first row will be considered to be data
     *
     * @return array
     *   list of datasets (array), one per row
     */
    protected function readCSV($input_stream, $encoding = 'UTF-8', $separator = ';', $column_mapping = null, $cap = null, $headers = null)
    {

        // read headers
        if (!isset($headers)) {
            $headers = fgetcsv($input_stream, 0, $separator);
        }
        $indices = [];
        foreach ($headers as $index => $header) {
            $indices[$index] = $header;
        }

        // read data
        $records = [];
        while ($record = fgetcsv($input_stream, 0, $separator)) {
            $labeled_record = [];
            foreach ($indices as $index => $header) {
                $raw_data = $record[$index];
                if ($encoding) {
                    $raw_data = iconv($encoding, "UTF-8", $raw_data);
                }
                $labeled_record[$header] = $raw_data;
            }

            // apply column mapping to record
            if ($column_mapping) {
                $mapped_record = [];
                foreach ($column_mapping as $old_column => $new_column) {
                    $mapped_record[$new_column] = $labeled_record[$old_column] ?? null;
                }
                $labeled_record = $mapped_record;
            }

            $records[] = $labeled_record;

            // check cap
            if ($cap && count($records) >= $cap) {
                break;
            }
        }

        // close file
        fclose($input_stream);

        // return records
        return $records;
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
            $target_attribute = $mapping[$attribute] ?? $attribute;
            $subset[$target_attribute] = $record[$attribute] ?? '';
        }
        return $subset;
    }
}
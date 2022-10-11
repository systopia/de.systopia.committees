<?php

use CRM_Committees_ExtensionUtil as E;

/**
 * Form controller class
 *
 * @see https://docs.civicrm.org/dev/en/latest/framework/quickform/
 */
class CRM_Committees_Form_Import extends CRM_Core_Form
{
    public function buildQuickForm()
    {
        $this->setTitle("Import Committee File");

        // compile importer list
        $importer_list = [];
        foreach (CRM_Committees_Plugin_Importer::getAvailableImporters() as $importer) {
            /** @var array $importer */
            $importer_list[$importer['key']] = $importer['display_name'];
        }
        $this->add(
            'select',
            'importer',
            E::ts("File Importer"),
            $importer_list,
            true,
            ['class' => 'crm-select2 huge']
        );

        // compile syncer list
        $syncer_list = [];
        foreach (CRM_Committees_Plugin_Syncer::getAvailableSyncers() as $syncer) {
            /** @var array $syncer */
            $syncer_list[$syncer['key']] = $syncer['display_name'];
        }
        $this->add(
            'select',
            'syncer',
            E::ts("Data Model"),
            $syncer_list,
            true,
            ['class' => 'crm-select2 huge']
        );

        $this->add(
            'file',
            'import_file',
            ts('Import Data File'),
            ['size' => 30, 'maxlength' => 255],
            TRUE
        );

        $max_size = CRM_Utils_Number::formatUnitSize('8M', TRUE);
        $this->setMaxFileSize($max_size);

        // default values:
        $this->setDefaults([
               'importer' => Civi::settings()->get('committees_last_importer'),
               'syncer' => Civi::settings()->get('committees_last_syncer'),
           ]);

        // add the buttons
        $this->addButtons([
              [
                  'type' => 'submit',
                  'name' => E::ts('Upload and Import'),
                  'isDefault' => true,
              ],
          ]);

        parent::buildQuickForm();
    }

    /**
     * Validates form values, specifically the requirements
     *
     * @return bool
     *   Whether the form validates.
     */
    public function validate()
    {
        $values = $this->exportValues();

        // check importer
        $importer_key = $values['importer'];
        $importers = CRM_Committees_Plugin_Importer::getAvailableImporters();
        if (empty($importers[$importer_key])) {
            $this->_errors['importer'] = E::ts("Importer not found");
        } elseif (!class_exists($importers[$importer_key]['class'])) {
            $this->_errors['importer'] = E::ts("Class not found");
        } else {
            // check requirements
            $importer = new $importers[$importer_key]['class']();
            $missing_requirements = $importer->getMissingRequirements();
            if (!empty($missing_requirements)) {
                $missing_requirement = reset($missing_requirements);
                $this->_errors['importer'] = $missing_requirement['label'];
            }
        }

        // check syncer
        $syncer_key = $values['syncer'];
        $syncers = CRM_Committees_Plugin_Syncer::getAvailableSyncers();
        if (empty($syncers[$syncer_key])) {
            $this->_errors['syncer'] = E::ts("Importer not found");
        } elseif (!class_exists($syncers[$syncer_key]['class'])) {
            $this->_errors['syncer'] = E::ts("Class not found");
        } else {
            // check requirements
            $syncer = new $syncers[$syncer_key]['class']();
            $missing_requirements = $syncer->getMissingRequirements();
            if (!empty($missing_requirements)) {
                $missing_requirement = reset($missing_requirements);
                $this->_errors['syncer'] = $missing_requirement['label'];
            }
        }

        return parent::validate();
    }


    public function postProcess()
    {
        // store new settings
        $values = $this->exportValues();
        Civi::settings()->set('committees_last_importer', $values['importer']);
        Civi::settings()->set('committees_last_syncer', $values['syncer']);

        // get importer
        $importers = CRM_Committees_Plugin_Importer::getAvailableImporters();
        /** @var CRM_Committees_Plugin_Importer $importer */
        $importer = new $importers[$values['importer']]['class']();

        $syncers = CRM_Committees_Plugin_Syncer::getAvailableSyncers();
        /** @var \CRM_Committees_Plugin_Syncer $syncer */
        $syncer = new $syncers[$values['syncer']]['class']();

        // todo: move all of this to another place?
        // todo: verify type as well

        // probe file
        $file = $this->_submitFiles['import_file'];
        if (!$importer->probeFile($file['tmp_name'])) {
            // this is not our file!
            $this->reportErrors($importer->getErrors());

        } else {
            // let's import & sync
            $importer->log("Starting importer " . get_class($importer) . " on file '{$file['name']}'...");
            $importer->importModel($file['tmp_name']);
            $model = $importer->getModel();
            $syncer->log("Starting syncer " . get_class($syncer));
            $syncer->syncModel($model);

            // done.
            $download_link = $syncer->getDownloadLink();
            if ($download_link) {
                CRM_Core_Session::setStatus(
                    E::ts("You can download a log of the process here: <a href=\"%1\"><code>%2</code></a>.", [
                        1 => $download_link,
                        2 => CRM_Committees_Plugin_Base::getCurrentLogFileName()
                    ]),
                    E::ts("Import/Sychronisation Completed."),
                    'info',
                    ['timeout' => 0]
                );
            } else {
                CRM_Core_Session::setStatus(
                    E::ts("You can find a log of the process here: <code>%1</code>.", [1 => $importer->getCurrentLogFile()]),
                    E::ts("Import/Sychronisation Completed."),
                    'info',
                    ['timeout' => 0]
                );
            }
        }
    }

    /**
     * Render all errors
     */
    protected function reportErrors($errors)
    {
        foreach ($errors as $error) {
            CRM_Core_Session::setStatus(
                $error['description'],
                E::ts("Import Error"),
                $error['level']
            );
        }
    }
}

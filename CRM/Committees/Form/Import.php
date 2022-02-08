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

        $this->add(
            'select',
            'importer',
            E::ts("File Importer"),
            CRM_Committees_Plugin_Importer::getAvailableImporters(),
            true,
            ['class' => 'crm-select2 huge']
        );

        $this->add(
            'select',
            'syncer',
            E::ts("Data Model"),
            CRM_Committees_Plugin_Syncer::getAvailableSyncers(),
            true,
            ['class' => 'crm-select2 huge']
        );

        $this->add(
            'file',
            'import_file',
            ts('Import Data File'),
            'size=30 maxlength=255',
            TRUE
        );

        $max_size = CRM_Utils_Number::formatUnitSize(1024 * 1024 * 8, true);
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
        if (!class_exists($values['importer'])) {
            $this->_errors['importer'] = E::ts("Class not found");
        } else {
            /** @var CRM_Committees_Plugin_Syncer $importer */
            $importer = new $values['importer']();
            $importer->checkRequirements();
            $missing_requirements = $importer->getMissingRequirements();
            if (!empty($missing_requirements)) {
                $missing_requirement = reset($missing_requirements);
                $this->_errors['importer'] = $missing_requirement['label'];
            }
        }

        // check syncer
        if (!class_exists($values['syncer'])) {
            $this->_errors['syncer'] = E::ts("Class not found");
        } else {
            /** @var CRM_Committees_Plugin_Syncer $syncer */
            $syncer = new $values['syncer']();
            $syncer->checkRequirements();
            $missing_requirements = $syncer->getMissingRequirements();
            if (!empty($missing_requirements)) {
                $missing_requirement = reset($missing_requirements);
                $this->_errors['syncer'] = $missing_requirement['description'];
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

        // probe file
        if (    class_exists($values['importer'])
             && class_exists($values['syncer'])
             && is_subclass_of($values['importer'], 'CRM_Committees_Plugin_Importer')
             && is_subclass_of($values['syncer'], 'CRM_Committees_Plugin_Syncer')) {

            // todo: move to another place?
            // todo: verify type as well
            $file = $this->_submitFiles['import_file'];
            /** @var CRM_Committees_Plugin_Importer $importer */
            $importer = new $values['importer']();

            /** @var \CRM_Committees_Plugin_Syncer $syncer */
            $syncer = new $values['syncer']();
            if (!$importer->probeFile($file['tmp_name'])) {
                // this is not our file!
                $this->reportErrors($importer->getErrors());

            } else {
                // let's import & sync
                $importer->log("Starting importer " . get_class($importer));
                $importer->importModel($file['tmp_name']);
                $model = $importer->getModel();
                $syncer->log("Starting syncer " . get_class($syncer));
                $syncer->syncModel($model);
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
                $error['message'],
                E::ts("Import Error"),
                $error['level']
            );
        }
    }
}

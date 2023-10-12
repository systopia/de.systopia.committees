<?php
/*-------------------------------------------------------+
| SYSTOPIA Committee Framework                           |
| Copyright (C) 2021-2023 SYSTOPIA                       |
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
 * Simple APIv3 action to run synchronisation
 */
function civicrm_api3_committee_sync($params) {
    // get importer
    $importers = CRM_Committees_Plugin_Importer::getAvailableImporters();
    $importer = new $importers[$params['importer_id']]['class']();

    // get syncer
    $syncers = CRM_Committees_Plugin_Syncer::getAvailableSyncers();
    $syncer = new $syncers[$params['syncer_id']]['class']();

    // run
    $importer->importModel($params['import_file']);
    $model = $importer->getModel();
    $syncer->syncModel($model);

    // check for errors
    if ($importer->getErrors() || $syncer->getErrors()) {
        return civicrm_api3_create_error("Errors: " . implode(', ', $importer->getErrors() + $syncer->getErrors()), [
            'log' => $importer->getCurrentLogFile(),
        ]);
    } else {
        return civicrm_api3_create_success(1, $params, 'Committee', 'sync', $null, [
            'log' => $importer->getCurrentLogFile(),
        ]);
    }
}

/**
 * API3 action specs
 */
function _civicrm_api3_committee_sync_spec(&$params) {
  $params['importer_id']['api.required'] = 1;
  $params['syncer_id']['api.required'] = 1;
  $params['import_file']['api.required'] = 0;
}

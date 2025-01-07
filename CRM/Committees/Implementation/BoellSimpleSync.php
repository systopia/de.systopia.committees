<?php
/*-------------------------------------------------------+
| Heinrich-Böll-Stiftung Importer (Based on OxfamSimple) |
| Copyright (C) 2025 SYSTOPIA                            |
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
 * Syncer for KuerschnerCsvImporter parliamentary committee model
 */
class CRM_Committees_Implementation_BoellSimpleSync extends CRM_Committees_Implementation_OxfamSimpleSync
{

    public function syncModel($model, $transaction = false)
    {
        // anything custom to do here?
        parent::syncModel($model, $transaction);
    }

    /**
     * Get the civicrm location type for the give kuerschner address type
     *
     * @param string $kuerschner_location_type
     *   should be one of 'Parlament' / 'Regierung' / 'Wahlkreis'
     *
     * @return string|null
     *   return the location type name or ID, or null/empty to NOT import
     */
    protected function disabled_getAddressLocationType($kuerschner_location_type)
    {
        switch ($kuerschner_location_type) {
            case CRM_Committees_Implementation_KuerschnerCsvImporter::LOCATION_TYPE_PARLIAMENT:
                return 'Parlament';

            default:
                return parent::getAddressLocationType($kuerschner_location_type);
        }
    }

    /**
     * Get the organization subtype for committees
     *
     * @return string
     *   the subtype name or null/empty string
     */
    protected function getCommitteeSubType()
    {
        return null; // 'Organization', no subtype;
    }
}
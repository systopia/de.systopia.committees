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

/**
 * Base for all syncers. Syncers are able to export the given internal model,
 *  and apply it to CiviCRM. In that process, CiviCRM entities (like individuals,
 *  organisations, relationships, etc.) will be created, altered or deleted.
 */
abstract class CRM_Committees_Plugin_Syncer extends CRM_Committees_Plugin_Base
{
    /**
     * Return a list of the available importers, represented by the implementation class name
     *
     * @return string[]
     */
    public static function getAvailableSyncers()
    {
        // todo: gather this through Symfony hook
        return [
            'CRM_Committees_Implementation_SessionSyncer'
        ];
    }
}
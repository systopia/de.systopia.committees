<?php
/*-------------------------------------------------------+
| SYSTOPIA Committee Synchronisation                     |
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


require_once 'committees.civix.php';

// phpcs:disable
use CRM_Committees_ExtensionUtil as E;

// phpcs:enable


/**
 * Implements hook_civicrm_config().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_config/
 */
function committees_civicrm_config(&$config)
{
    _committees_civix_civicrm_config($config);

    // register for Committees.register_modules event
    Civi::dispatcher()->addListener(
        'civi.committees.register_modules',
        ['CRM_Committees_Plugin_Importer', 'registerBuiltInImporters']
    );
    Civi::dispatcher()->addListener(
        'civi.committees.register_modules',
        ['CRM_Committees_Plugin_Syncer', 'registerBuiltInSyncers']
    );
}

/**
 * Implements hook_civicrm_install().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_install
 */
function committees_civicrm_install()
{
    _committees_civix_civicrm_install();
}

/**
 * Implements hook_civicrm_enable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_enable
 */
function committees_civicrm_enable()
{
    _committees_civix_civicrm_enable();
}

/**
 * Implements hook_civicrm_navigationMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_navigationMenu
 */
function committees_civicrm_navigationMenu(&$menu)
{
    _committees_civix_insert_navigation_menu($menu, 'Contacts', [
        'label' => E::ts('Import/Synchronise Committees'),
        'name' => 'sync_committees',
        'url' => 'civicrm/committees/upload',
        'permission' => 'import contacts',
        'operator' => 'OR',
        'separator' => 0,
    ]);
    _committees_civix_navigationMenu($menu);
}

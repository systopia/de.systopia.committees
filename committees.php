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
 * Implements hook_civicrm_xmlMenu().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_xmlMenu
 */
function committees_civicrm_xmlMenu(&$files)
{
    _committees_civix_civicrm_xmlMenu($files);
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
 * Implements hook_civicrm_postInstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_postInstall
 */
function committees_civicrm_postInstall()
{
    _committees_civix_civicrm_postInstall();
}

/**
 * Implements hook_civicrm_uninstall().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_uninstall
 */
function committees_civicrm_uninstall()
{
    _committees_civix_civicrm_uninstall();
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
 * Implements hook_civicrm_disable().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_disable
 */
function committees_civicrm_disable()
{
    _committees_civix_civicrm_disable();
}

/**
 * Implements hook_civicrm_upgrade().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_upgrade
 */
function committees_civicrm_upgrade($op, CRM_Queue_Queue $queue = null)
{
    return _committees_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implements hook_civicrm_managed().
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_managed
 */
function committees_civicrm_managed(&$entities)
{
    _committees_civix_civicrm_managed($entities);
}

/**
 * Implements hook_civicrm_caseTypes().
 *
 * Add CiviCase types provided by this extension.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_caseTypes
 */
function committees_civicrm_caseTypes(&$caseTypes)
{
    _committees_civix_civicrm_caseTypes($caseTypes);
}

/**
 * Implements hook_civicrm_angularModules().
 *
 * Add Angular modules provided by this extension.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_angularModules
 */
function committees_civicrm_angularModules(&$angularModules)
{
    // Auto-add module files from ./ang/*.ang.php
    _committees_civix_civicrm_angularModules($angularModules);
}

/**
 * Implements hook_civicrm_alterSettingsFolders().
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_alterSettingsFolders
 */
function committees_civicrm_alterSettingsFolders(&$metaDataFolders = null)
{
    _committees_civix_civicrm_alterSettingsFolders($metaDataFolders);
}

/**
 * Implements hook_civicrm_entityTypes().
 *
 * Declare entity types provided by this module.
 *
 * @link https://docs.civicrm.org/dev/en/latest/hooks/hook_civicrm_entityTypes
 */
function committees_civicrm_entityTypes(&$entityTypes)
{
    _committees_civix_civicrm_entityTypes($entityTypes);
}

/**
 * Implements hook_civicrm_themes().
 */
function committees_civicrm_themes(&$themes)
{
    _committees_civix_civicrm_themes($themes);
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

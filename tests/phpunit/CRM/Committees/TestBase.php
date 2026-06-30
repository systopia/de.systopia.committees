<?php
/*-------------------------------------------------------+
| SYSTOPIA Committee Framework                           |
| Copyright (C) 2022 SYSTOPIA                            |
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

use Civi\Test\Api3TestTrait;
use Civi\Test\HeadlessInterface;
use Civi\Core\HookInterface;

use CRM_Committees_ExtensionUtil as E;

/**
 * This is the test base class with lots of utility functions
 *
 * @group headless
 */
class CRM_Committees_TestBase extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface {
  use Api3TestTrait {
    callAPISuccess as protected traitCallAPISuccess;
  }

  use CRM_Committees_Tools_IdTrackerTrait {
    clearCaches as idTrackerTraitClearCaches;
  }

  /**
   * @var CRM_Core_Transaction current transaction */
  protected $transaction = NULL;

  public function setUpHeadless() {
    // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
    // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
    return \Civi\Test::headless()
      ->install(['de.systopia.identitytracker'])
      ->installMe(__DIR__)
      ->apply();
  }

  public function setUp(): void {
    parent::setUp();
  }

  public function tearDown(): void {
    CRM_Committees_CustomData::flushCashes();
    $this->deleteAllContacts();
    $this->clearIdTracker();
    self::idTrackerTraitClearCaches();
    Civi::cache()->clear();
    parent::tearDown();
  }

  /**
   * Import the given
   *
   * @param string $syncer_id
   *   id/key of the syncer to be used
   *
   * @param string $importer_id
   *   id/key of the importer to be used
   *
   * @param string|null $import_file
   *   file path to be passed to the importer (if required)
   *
   * @param bool $fail_on_errors
   *   should this fail if errors expected?
   *
   * @param bool $clear_caches
   *   should the caches be cleared? highly recommended...
   *
   * @return array
   */
  public function sync(string $syncer_id, string $importer_id, ?string $import_file = NULL, $fail_on_errors = TRUE, $clear_caches = TRUE) {
    // clear caches
    if ($clear_caches) {
      self::idTrackerTraitClearCaches();
    }

    // check importer
    $importers = CRM_Committees_Plugin_Importer::getAvailableImporters();
    self::assertArrayHasKey($importer_id, $importers, "Importer {$importer_id} not available.");
    /** @phpstan-var CRM_Committees_Plugin_Importer $importer */
    $importer = new $importers[$importer_id]['class']();

    // check syncer exists
    $syncers = CRM_Committees_Plugin_Syncer::getAvailableSyncers();
    self::assertArrayHasKey($syncer_id, $syncers, "Syncer {$syncer_id} not available.");
    /** @phpstan-var CRM_Committees_Plugin_Syncer $syncer */
    $syncer = new $syncers[$syncer_id]['class']();

    // run
    $importer->importModel($import_file);
    $model = $importer->getModel();
    $syncer->syncModel($model);

    // check for errors
    if ($fail_on_errors) {
      self::assertEmpty($importer->getErrors(), "Importer 'de.oxfam.kuerschner' reports errors: " . implode(', ', $importer->getErrorMessages(TRUE)));
      self::assertEmpty($syncer->getErrors(), "Syncer 'de.oxfam.kuerschner.syncer.bund' reports errors: " . implode(', ', $syncer->getErrorMessages(TRUE)));
    }

    return [$importer, $syncer];
  }

  /**
   * Assert a set of properties in the given array
   *
   * @var array $expected
   *   list of name => value pairs to be expected in the $actual array
   *
   * @var array $actual
   *   data to be tested for the $expected params
   */
  public function assertProperties(array $expected, array $actual) {
    foreach ($expected as $property => $value) {
      self::assertArrayHasKey($property, $actual, "Expected property '{$property}' not found.");
      self::assertEquals($value, $actual[$property]);
    }
  }

  /**
   * Simply delete all contacts with a contact ID > 1
   * @return void
   */
  public function deleteAllContacts() {
    $all_contacts = $this->traitCallAPISuccess(
        'Contact',
        'get',
        ['option.limit' => 0, 'return' => 'id']
    );
    foreach ($all_contacts['values'] as $contact) {
      $this->traitCallAPISuccess('Contact', 'delete', ['id' => $contact['id']]);
    }
  }

  /**
   * Delete all entries in the ID tracker table
   *
   * @return void
   */
  public function clearIdTracker() {
    CRM_Core_DAO::executeQuery('DELETE FROM ' . CRM_Identitytracker_Configuration::GROUP_TABLE);
  }

}

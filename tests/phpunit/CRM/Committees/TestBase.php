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
use Civi\Test\HookInterface;
use Civi\Test\TransactionalInterface;

use CRM_Committees_ExtensionUtil as E;

/**
 * This is the test base class with lots of utility functions
 *
 * @group headless
 */
class CRM_Committees_TestBase extends \PHPUnit\Framework\TestCase implements HeadlessInterface, HookInterface
{
    use Api3TestTrait {
        callAPISuccess as protected traitCallAPISuccess;
    }

    /** @var CRM_Core_Transaction current transaction */
    protected $transaction = null;

    public function setUpHeadless()
    {
        // Civi\Test has many helpers, like install(), uninstall(), sql(), and sqlFile().
        // See: https://docs.civicrm.org/dev/en/latest/testing/phpunit/#civitest
        return \Civi\Test::headless()
            ->install(['de.systopia.identitytracker'])
            ->installMe(__DIR__)
            ->apply();
    }


    public function setUp()
    {
        parent::setUp();
    }

    public function tearDown()
    {
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
    public function sync(string $syncer_id, string $importer_id, string $import_file = null, $fail_on_errors = true, $clear_caches = true)
    {
        // clear caches
        if ($clear_caches) {
            CRM_Committees_Tools_IdTrackerTrait::clearCaches();
        }

        // check importer
        $importers = CRM_Committees_Plugin_Importer::getAvailableImporters();
        $this->assertArrayHasKey($importer_id, $importers, "Importer {$importer_id} not available.");
        $importer = new $importers[$importer_id]['class']();

        // check syncer exists
        $syncers = CRM_Committees_Plugin_Syncer::getAvailableSyncers();
        $this->assertArrayHasKey($syncer_id, $syncers, "Syncer {$syncer_id} not available.");
        $syncer = new $syncers[$syncer_id]['class']();

        // run
        $importer->importModel($import_file);
        $model = $importer->getModel();
        $syncer->syncModel($model);

        // check for errors
        if ($fail_on_errors) {
            $this->assertEmpty($importer->getErrors(), "Importer 'de.oxfam.kuerschner' reports errors");
            $this->assertEmpty($syncer->getErrors(), "Syncer 'de.oxfam.kuerschner.syncer.bund' reports errors");
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
    public function assertProperties(array $expected, array $actual)
    {
        foreach ($expected as $property => $value) {
            $this->assertArrayHasKey($property, $actual, "Expected property '{$property}' not found.");
            $this->assertEquals($value, $actual[$property]);
        }
    }
}
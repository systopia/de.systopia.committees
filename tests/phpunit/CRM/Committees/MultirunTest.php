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
use Civi\Test\TransactionalInterface;
use CRM_Committees_ExtensionUtil as E;

/**
 * First simple tests about the committee extension
 *
 * CAUTION: Multirun tests have to be run without TransactionalInterface,
 *  because there seems to be some issues with contact subtypes
 *
 * @group headless
 */
class CRM_Committees_MultirunTest extends CRM_Committees_TestBase
{
    use Api3TestTrait {
        callAPISuccess as protected traitCallAPISuccess;
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
     * Test the basic file format described in the docs
     */
    public function testFileFormat01()
    {
        /** @var $importer \CRM_Committees_Implementation_KuerschnerCsvImporter */
        /** @var $syncer \CRM_Committees_Implementation_OxfamSimpleSync */

        // run the importer
        list($importer, $syncer) =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path('tests/resources/kuerschner/bundestag-02.csv')
            );
    }

    /**
     * Test the file format with the functions and staff fields
     *
     * @see https://projekte.systopia.de/issues/18225
     */
    public function testFileFormat02()
    {
        /** @var $importer \CRM_Committees_Implementation_KuerschnerCsvImporter */
        /** @var $syncer \CRM_Committees_Implementation_OxfamSimpleSync */

        // run the importer
        list($importer, $syncer) =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path('tests/resources/kuerschner/bundestag-03_extd.csv')
            );
    }

    /**
     * Just load a minimal import file and check if the data is present
     */
    public function testUpdateFileImport()
    {
        /** @var $importer \CRM_Committees_Implementation_KuerschnerCsvImporter */
        /** @var $syncer \CRM_Committees_Implementation_OxfamSimpleSync */

        // run the importer
        list($importer, $syncer) =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path('tests/resources/kuerschner/bundestag-01.csv')
            );

        // run the update
        list($importer, $syncer) =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path('tests/resources/kuerschner/bundestag-02.csv')
            );


    }
}

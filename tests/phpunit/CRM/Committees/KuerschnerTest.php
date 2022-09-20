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

const FILE_WITH_TITLE_AND_STAFF =  'tests/resources/kuerschner/bundestag-04-01.csv';
const FILE_WITHOUT_TITLE_AND_STAFF =  'tests/resources/kuerschner/bundestag-04-02.csv';

/**
 * First simple tests about the committee extension
 *
 * CAUTION: Multirun tests have to be run without TransactionalInterface,
 *  because there seems to be some issues with contact subtypes
 *
 * @group headless
 */
class CRM_Committees_KuerschnerTest extends CRM_Committees_TestBase
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
        // bring out the big guns:
        CRM_Core_DAO::executeQuery("TRUNCATE TABLE civicrm_value_lobby_infos;");
    }

    /**
     * Test a single import without title and staff, to make sure the following tests make sense
     */
    public function testImportWithTitleAndStaff()
    {
        /** @var $importer \CRM_Committees_Implementation_KuerschnerCsvImporter */
        /** @var $syncer \CRM_Committees_Implementation_OxfamSimpleSync */

        // run the importer
        [$importer, $syncer] =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path(FILE_WITH_TITLE_AND_STAFF)
            );

        // get the field names
        CRM_Committees_CustomData::flushCashes();
        $mop_salutation_field = CRM_Committees_CustomData::getCustomFieldKey('Lobby_Infos', 'mop_salutation');
        $mop_staff_field = CRM_Committees_CustomData::getCustomFieldKey('Lobby_Infos', 'mop_staff');

        // load the contact
        $mop_id = $syncer->getIDTContactID(12994,CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_TYPE, CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_PREFIX);
        $mop = $this->traitCallAPISuccess(
            'Contact',
            'getsingle',
            [
                'id' => $mop_id,
                'return' => [$mop_salutation_field, $mop_staff_field]]
        );

        $this->assertArrayHasKey($mop_salutation_field, $mop, 'This contact should have the salutation set');
        $this->assertNotEmpty($mop[$mop_salutation_field], 'This contact should have the salutation set');
        $this->assertArrayHasKey($mop_staff_field, $mop, 'This contact should have the staff set');
        $this->assertNotEmpty($mop[$mop_staff_field], 'This contact should have the staff set');
    }

    /**
     * Test a single import with title and staff, to make sure the following tests make sense
     */
    public function testImportWithoutTitleAndStaff()
    {
        /** @var $importer \CRM_Committees_Implementation_KuerschnerCsvImporter */
        /** @var $syncer \CRM_Committees_Implementation_OxfamSimpleSync */

        // run the importer
        [$importer, $syncer] =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path(FILE_WITHOUT_TITLE_AND_STAFF)
            );

        // get the filed names
        CRM_Committees_CustomData::flushCashes();
        $mop_salutation_field = CRM_Committees_CustomData::getCustomFieldKey('Lobby_Infos', 'mop_salutation');
        $mop_staff_field = CRM_Committees_CustomData::getCustomFieldKey('Lobby_Infos', 'mop_staff');

        // load the contact
        $mop_id = $syncer->getIDTContactID(12994,CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_TYPE, CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_PREFIX);
        $mop = $this->traitCallAPISuccess(
            'Contact',
            'getsingle',
            [
                'id' => $mop_id,
                'return' => [$mop_salutation_field, $mop_staff_field]]
        );

        $this->assertArrayHasKey($mop_salutation_field, $mop, 'This contact should have the salutation field');
        $this->assertEmpty($mop[$mop_salutation_field], 'This contact should not have the salutation set');
        $this->assertArrayHasKey($mop_staff_field, $mop, 'This contact should have the staff field');
        $this->assertEmpty($mop[$mop_staff_field], 'This contact should have the staff set');
    }

    /**
     * See if the update mechanism works:
     *  first import with staff, then without
     */
    public function testFirstWithThenWithoutStaff()
    {
        /** @var $importer \CRM_Committees_Implementation_KuerschnerCsvImporter */
        /** @var $syncer \CRM_Committees_Implementation_OxfamSimpleSync */

        // IMPORT THE FIRST FILE (with staff)
        [$importer, $syncer] =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path(FILE_WITH_TITLE_AND_STAFF)
            );

        // make sure staff is there
        CRM_Committees_CustomData::flushCashes();
        $mop_salutation_field = CRM_Committees_CustomData::getCustomFieldKey('Lobby_Infos', 'mop_salutation');
        $mop_staff_field = CRM_Committees_CustomData::getCustomFieldKey('Lobby_Infos', 'mop_staff');

        // load the contact
        $mop_id = $syncer->getIDTContactID(
            12994,
            CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_TYPE,
            CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_PREFIX
        );
        $mop = $this->traitCallAPISuccess(
            'Contact',
            'getsingle',
            [
                'id' => $mop_id,
                'return' => [$mop_salutation_field, $mop_staff_field]
            ]
        );

        $this->assertArrayHasKey($mop_salutation_field, $mop, 'This contact should have the salutation set');
        $this->assertNotEmpty($mop[$mop_salutation_field], 'This contact should have the salutation set');
        $this->assertArrayHasKey($mop_staff_field, $mop, 'This contact should have the staff set');
        $this->assertNotEmpty($mop[$mop_staff_field], 'This contact should have the staff set');


        // IMPORT THE SECOND FILE (without staff)
        [$importer, $syncer] =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path(FILE_WITHOUT_TITLE_AND_STAFF)
            );

        // make sure staff is there
        CRM_Committees_CustomData::flushCashes();
        $mop_salutation_field = CRM_Committees_CustomData::getCustomFieldKey('Lobby_Infos', 'mop_salutation');
        $mop_staff_field = CRM_Committees_CustomData::getCustomFieldKey('Lobby_Infos', 'mop_staff');

        // reload the contact
        $mop = $this->traitCallAPISuccess(
            'Contact',
            'getsingle',
            [
                'id' => $mop_id,
                'return' => [$mop_salutation_field, $mop_staff_field]
            ]
        );

        $this->assertArrayHasKey($mop_salutation_field, $mop, 'This contact should have the salutation key');
        $this->assertEmpty($mop[$mop_salutation_field], 'This contact should NOT have the salutation set');
        $this->assertArrayHasKey($mop_staff_field, $mop, 'This contact should have the staff key');
        $this->assertEmpty($mop[$mop_staff_field], 'This contact should NOT have the staff set');

    }
}
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
const FILE_WITH_MOP_WITH_STAFF =  'tests/resources/kuerschner/bundestag-05-01.csv';
const FILE_WITHOUT_MOP =  'tests/resources/kuerschner/bundestag-05-02.csv';

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

        $this->assertArrayHasKey($mop_salutation_field, $mop, 'This contact should have the salutation field');
        $this->assertEmpty($mop[$mop_salutation_field], 'This contact should NOT have the salutation set');
        $this->assertArrayHasKey($mop_staff_field, $mop, 'This contact should have the staff field');
        $this->assertEmpty($mop[$mop_staff_field], 'This contact should NOT have the staff set');
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

    /**
     * Make sure that the contact's MOP aditional data is wiped when leaving the parliament
     *
     * @see https://projekte.systopia.de/issues/18225#Zu-Punkt-56
     */
    public function testDataWipedWhenMemberLeftParliament()
    {
        /** @var $importer \CRM_Committees_Implementation_KuerschnerCsvImporter */
        /** @var $syncer \CRM_Committees_Implementation_OxfamSimpleSync */

        // IMPORT THE FIRST FILE (with staff)
        [$importer, $syncer] =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path(FILE_WITH_MOP_WITH_STAFF)
            );

        // make sure staff is there
        CRM_Committees_CustomData::flushCashes();
        $mop_salutation_field = CRM_Committees_CustomData::getCustomFieldKey('Lobby_Infos', 'mop_salutation');
        $mop_staff_field = CRM_Committees_CustomData::getCustomFieldKey('Lobby_Infos', 'mop_staff');

        // load the contact
        $mop_id = $syncer->getIDTContactID(
            12995,
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

        $current_salutation_value = $mop[$mop_salutation_field] ?? '';
        $this->assertNotEmpty($current_salutation_value, "This MOP should have a title.");
        $current_staff_value = $mop[$mop_staff_field] ?? '';
        $this->assertNotEmpty($current_staff_value, "This MOP should have staff.");


        // IMPORT THE SECOND FILE, DISABLE THEM (not there)
        [$importer, $syncer] =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path(FILE_WITHOUT_MOP)
            );
        CRM_Committees_CustomData::flushCashes();

        // load the contact
        $mop_id = $syncer->getIDTContactID(
            12995,
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

        $current_salutation_value = $mop[$mop_salutation_field] ?? '';
        $this->assertEmpty($current_salutation_value, "This the salutation should've been cleared after the member left the parliament.");
        $current_staff_value = $mop[$mop_staff_field] ?? '';
        $this->assertEmpty($current_staff_value, "This the staff should've been cleared after the member left the parliament.");
    }

    /**
     * Make sure that the contact's MOP additional data is wiped when leaving the parliament
     *
     * @see https://projekte.systopia.de/issues/18225#note-24
     */
    public function testReactivateMOPRelationship()
    {
        /** @var $importer \CRM_Committees_Implementation_KuerschnerCsvImporter */
        /** @var $syncer \CRM_Committees_Implementation_OxfamSimpleSync */

        // IMPORT THE FIRST FILE (active MOP)
        [$importer, $syncer] =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path(FILE_WITH_MOP_WITH_STAFF)
            );
        CRM_Committees_CustomData::flushCashes();

        // load the contact
        $mop_id = $syncer->getIDTContactID(
            12995,
            CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_TYPE,
            CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_PREFIX
        );

        // get the parliament ID
        $parliament_id = $syncer->getParliamentContactID($importer->getModel());
        $this->assertNotEmpty($parliament_id, "Couldn't identifiy the parliament contact.");

        // get the relationship
        $relationship_type_ids = array_values($syncer->getRoleToRelationshipTypeIdMapping());
        $relationships = $this->traitCallAPISuccess(
            'Relationship',
            'get',
            [
                'relationship_type_id' => ['IN' => $relationship_type_ids],
                'contact_id_a' => $mop_id,
                'contact_id_b' => ['IN' => [$parliament_id]],
                'is_active' => 1,
            ]
        );
        $this->assertNotEmpty($relationships['values'], "There should be an active relationship to the parliament");


        // IMPORT THE SECOND FILE, MOP should leave
        [$importer, $syncer] =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path(FILE_WITHOUT_MOP)
            );
        CRM_Committees_CustomData::flushCashes();

        // check if MOP has disabled relationship
        $relationships = $this->traitCallAPISuccess(
            'Relationship',
            'get',
            [
                'relationship_type_id' => ['IN' => $relationship_type_ids],
                'contact_id_a' => $mop_id,
                'contact_id_b' => ['IN' => [$parliament_id]],
                'is_active' => 1,
            ]
        );
        $this->assertEmpty($relationships['values'], "There should NOT be an active relationship to the parliament");

        // IMPORT THE FIRST FILE AGAIN (active MOP again)
        [$importer, $syncer] =
            $this->sync(
                'de.oxfam.kuerschner.syncer.bund',
                'de.oxfam.kuerschner',
                E::path(FILE_WITH_MOP_WITH_STAFF)
            );
        CRM_Committees_CustomData::flushCashes();

        // get the relationship
        $relationship_type_ids = array_values($syncer->getRoleToRelationshipTypeIdMapping());
        $relationships = $this->traitCallAPISuccess(
            'Relationship',
            'get',
            [
                'relationship_type_id' => ['IN' => $relationship_type_ids],
                'contact_id_a' => $mop_id,
                'contact_id_b' => ['IN' => [$parliament_id]],
                'is_active' => 1,
            ]
        );
        $this->assertNotEmpty($relationships['values'], "There should be an active relationship to the parliament again");
    }
}

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
 * @group headless
 */
class CRM_Committees_BasicTest extends CRM_Committees_TestBase implements TransactionalInterface
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
     * Just load a minimal import file and check if the data is present
     */
    public function testBasicFileImport()
    {
        /** @var $importer \CRM_Committees_Implementation_KuerschnerCsvImporter */
        /** @var $syncer \CRM_Committees_Implementation_OxfamSimpleSync */
        list($importer, $syncer) =
            $this->sync(
            'de.oxfam.kuerschner.syncer.bund',
            'de.oxfam.kuerschner',
            E::path('tests/resources/kuerschner/bundestag-01.csv')
        );

        // load and compare contact1
        $contact1_id = $syncer->getIDTContactID(25948,CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_TYPE, CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_PREFIX);
        $contact1 = $this->traitCallAPISuccess(
            'Contact',
            'getsingle',
            ['id' => $contact1_id]
        );
        // verify if the values match the ones from the file
        $this->assertProperties(
            [
                'last_name' => 'UNBEKANNT',
                'first_name' => 'Stephanie',
                'phone' => '+49 30 227-11111',
                'email' => 'stephanie.unbekannt@bundestag.de',
                'postal_code' => '11011',
                'street_address' => 'Platz der Republik 1',
            ],
            $contact1
        );


        // load and compare contact2
        $contact2_id = $syncer->getIDTContactID(18361,CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_TYPE, CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_PREFIX);
        $contact2 = $this->traitCallAPISuccess(
            'Contact',
            'getsingle',
            ['id' => $contact2_id]
        );
        // verify if the values match the ones from the file
        $this->assertProperties(
            [
                'last_name' => 'UNKNOWN',
                'first_name' => 'Luise',
                'phone' => '+49 30 227-22222',
                'email' => 'luise.unknown@bundestag.de',
                'postal_code' => '11011',
                'street_address' => 'Platz der Republik 1',
            ],
            $contact2
        );

        // load and compare contact3
        $contact3_id = $syncer->getIDTContactID(34580,CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_TYPE, CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_PREFIX);
        $contact3 = $this->traitCallAPISuccess(
            'Contact',
            'getsingle',
            ['id' => $contact3_id]
        );
        // verify if the values match the ones from the file
        $this->assertProperties(
            [
                'last_name' => 'ICOGNITO',
                'first_name' => 'Andreas',
                'phone' => '+49 30 227-3333',
                'email' => 'andreas.incognito@bundestag.de',
                'postal_code' => '11011',
                'street_address' => 'Platz der Republik 1',
            ],
            $contact3
        );
    }
}

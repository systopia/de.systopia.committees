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
use CRM_Committees_ExtensionUtil as E;

/**
 * First simple tests about the committee extension
 *
 * @group headless
 */
class CRM_Committees_BasicTest extends CRM_Committees_TestBase
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
    public function testImportFile()
    {
        /** @var $importer \CRM_Committees_Implementation_KuerschnerCsvImporter */
        /** @var $syncer \CRM_Committees_Implementation_OxfamSimpleSync */
        list($importer, $syncer) =
            $this->sync(
            'de.oxfam.kuerschner.syncer.bund',
            'de.oxfam.kuerschner',
            E::path('tests/resources/kuerschner/bundestag-01.csv')
        );

        // load the contact
        $contact_id = $syncer->getIDTContactID(9680,CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_TYPE, CRM_Committees_Implementation_OxfamSimpleSync::ID_TRACKER_PREFIX);
        $contact = $this->traitCallAPISuccess(
            'Contact',
            'getsingle',
            ['id' => $contact_id]
        );

        // verify if the values match the ones from the file
        $this->assertProperties(
            [
                'last_name' => 'Sotte',
                'first_name' => 'Petra',
                'phone' => '+49 30 227-1231241',
                'email' => 'petra.sotte@bundestag.de',
                'postal_code' => '11011',
                'street_address' => 'Platz der Republik 1',
            ],
            $contact
        );
    }
}

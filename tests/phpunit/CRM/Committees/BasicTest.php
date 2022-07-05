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
     * Just create a contact, add an activity, recalculate,
     */
    public function testImportFile()
    {
        $this->sync(
            'de.oxfam.kuerschner.syncer.bund',
            'de.oxfam.kuerschner',
            'resources/kuerschner/bundestag-01.csv'
        );
    }
}

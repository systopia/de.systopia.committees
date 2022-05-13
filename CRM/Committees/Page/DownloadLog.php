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

use CRM_Committees_ExtensionUtil as E;

/**
 * Not a real page, downloader for a log file
 */
class CRM_Committees_Page_DownloadLog extends CRM_Core_Page
{
    public function run()
    {
        $key = CRM_Utils_Request::retrieve('key', 'String', $this, TRUE);
        $date = CRM_Utils_Request::retrieve('date', 'String', $this, TRUE);
        $file_content = CRM_Committees_Plugin_Base::getLogFileContent($date, $key);
        $file_name = CRM_Committees_Plugin_Base::getCurrentLogFileName($date);
        CRM_Utils_System::download($file_name, 'text/plain', $file_content);
    }
}

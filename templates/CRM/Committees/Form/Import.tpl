{*-------------------------------------------------------+
| SYSTOPIA Committee Framework                           |
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
+-------------------------------------------------------*}

<div id="help">Vielleicht vorher noch ein Backup machen...?</div>

<div class="crm-section">
  <div class="label">{$form.import_file.label}</div>
  <div class="content">{$form.import_file.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.importer.label}</div>
  <div class="content">{$form.importer.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-section">
  <div class="label">{$form.syncer.label}</div>
  <div class="content">{$form.syncer.html}</div>
  <div class="clear"></div>
</div>

<div class="crm-submit-buttons">
    {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

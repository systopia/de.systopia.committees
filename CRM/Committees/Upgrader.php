<?php

use CRM_Committees_ExtensionUtil as E;

final class CRM_Committees_Upgrader extends CRM_Extension_Upgrader_Base {

  public function upgrade_0001(): bool {
     $customGroupExists = (bool) CRM_Committees_CustomData::getGroupTable('political_membership_additional');
     if ($customGroupExists) {
       $this->ctx->log->info('Updating custom data structures for political memberships...');
       $customData = new CRM_Committees_CustomData(E::LONG_NAME);
       $customData->syncCustomGroup(
         E::path('resources/OxfamSimpleSync/custom_group_political_membership_additional.json')
       );
     }

     return TRUE;
  }

}

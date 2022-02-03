<?php
/*-------------------------------------------------------+
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
+--------------------------------------------------------*/

use CRM_Committees_ExtensionUtil as E;

/**
 * This trait adds features based on
 *  the "extended contact matcher" extension (XCM)
 *
 * @see https://github.com/systopia/de.systopia.xcm
 */
trait CRM_Committees_Tools_XcmTrait
{
    /**
     * Run the XCM with the given data
     *
     * @param array $contact_data
     *   the contact data to submit
     *
     * @param string $profile_name
     *   name of the XCM profile to use
     *
     * @return integer
     *   contact ID
     */
    public function runXCM($contact_data, $profile_name = null)
    {
        if (isset($profile_name)) {
            $contact_data['xcm_profile'] = $profile_name;
        }

        // run XCM
        // todo: error handling
        $result = civicrm_api3('Contact', 'getorcreate', $contact_data);
        return $result['id'];
    }

    /**
     * Check the XCM requirements
     *
     * @param CRM_Committees_Plugin_Base $plugin
     *   the plugin
     *
     * @param array XCM profiles required
     */
    public function checkXCMRequirements(CRM_Committees_Plugin_Base $plugin, $required_profiles = [])
    {
        if (!$plugin->extensionAvailable('de.systopia.xcm')) {
            // xcm not even installed
            $plugin->registerMissingRequirement('de.systopia.xcm',
                  E::ts("Extended Contact Matcher (XCM) extension missing"),
                  E::ts("Please install the <code>de.systopia.xcm</code> extension from <a href='https://github.com/systopia/de.systopia.xcm'>here</a>.")
            );
        } else {
            // make sure all the profiles are there
            foreach ($required_profiles as $required_profile) {
                if (!$this->xcmProfileExists($required_profile)) {
                    $plugin->registerMissingRequirement(
                        $required_profile,
                        E::ts("XCM Profile missing"),
                        E::ts("Please create a <code>%1</code> profile in the XCM configuration.", [1 => $required_profile])
                    );
                }
            }
        }
    }

    /**
     * Check if the given XCM profile exists
     *
     * @param string $profile_name
     *    internal profile name
     *
     * @return boolean
     *    true, if profile exists
     */
    public function xcmProfileExists($profile_name)
    {
        $profile_list = CRM_Xcm_Configuration::getProfileList();
        return !empty($profile_list[$profile_name]);
    }
}
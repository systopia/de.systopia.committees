<?php
/*-------------------------------------------------------+
| SYSTOPIA Committee Framework                           |
| Copyright (C) 2023 SYSTOPIA                            |
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
 * This trait adds features to extract a model form the data currently
 *   present in the CiviCRM db
 *
 */
trait CRM_Committees_Tools_ModelExtractionTrait
{
    /**
     * Extract the currently imported contacts from the CiviCRMs and add them to the 'present model'
     *
     * CAUTION: Requires the contacts to be identified in the DB via a contact_id attribute
     *
     * @param CRM_Committees_Model_Model $requested_model
     *   the model to be synced to this CiviCRM
     *
     * @param CRM_Committees_Model_Model $present_model
     *   a model to add the current contacts to, as extracted from the DB
     *
     * @param string $type
     *   phone, email or address
     *
     * @param array $formatters
     *   list of formatters to be applied to the extracted value
     */
    protected function extractCurrentDetails($requested_model, $present_model, $type, $formatters = [])
    {
        // some basic configurations for the different types
        $load_attributes = [
                'email' => ['contact_id', 'email', 'location_type_id'],
                'phone' => ['contact_id', 'phone', 'location_type_id', 'phone_type_id', 'phone_numeric'],
                'address' => ['contact_id', 'street_address', 'postal_code', 'city', 'location_type_id'],
                'website' => ['contact_id', 'url', 'website_type_id'],
        ];
        $copy_attributes = [
                'email' => ['email'],
                'phone' => ['phone', 'phone_numeric'],
                'address' => ['street_address', 'postal_code', 'city', 'supplemental_address_1', 'supplemental_address_2', 'supplemental_address_3'],
                'website' => ['url', 'website_type_id'],
        ];

        // check with all known CiviCRM contacts
        $contact_id_to_person_id = [];
        foreach ($present_model->getAllPersons() as $person) {
            /** @var CRM_Committees_Model_Person $person */
            $contact_id = (int) $person->getAttribute('contact_id');
            if ($contact_id) {
                $contact_id_to_person_id[$contact_id] = $person->getID();
            }
        }

        // stop here if there's no known contacts
        if (empty($contact_id_to_person_id)) {
            return [];
        }

        // load the given attributes
        $existing_details = $this->callApi3($type, 'get', [
                'contact_id' => ['IN' => array_keys($contact_id_to_person_id)],
                'return' => implode(',', $load_attributes[$type]),
                'option.limit' => 0,
        ]);

        // strip duplicates
        $data_by_id = [];
        foreach ($existing_details['values'] as $detail) {
            $person_id = $contact_id_to_person_id[$detail['contact_id']];
            $data = ['contact_id' => $person_id];
            foreach ($copy_attributes[$type] as $attribute) {
                if (isset($detail[$attribute]) && $detail[$attribute]) {
                    foreach ($formatters as $formatter) {
                        switch ($formatter) {
                            case 'strtolower':
                                $detail[$attribute] = strtolower($detail[$attribute]);
                                break;
                            default:
                                // do nothing
                        }
                    }
                    $data[$attribute] = $detail[$attribute];
                }
            }
            $key = implode('##', $data);
            $data_by_id[$key] = $data;
        }

        // finally, add all to the model
        foreach ($data_by_id as $data) {
            switch ($type) {
                case 'phone':
                    $present_model->addPhone($data);
                    break;
                case 'email':
                    $present_model->addEmail($data);
                    break;
                case 'address':
                    $present_model->addAddress($data);
                    break;
                default:
                    throw new Exception("Unknown type {$type} for extractCurrentDetails function.");
            }
        }
    }


}
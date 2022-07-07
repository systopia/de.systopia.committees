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

/**
 * The model containing all entities
 */
class CRM_Committees_Model_Model
{
    /** @var string default separated to be used for compound keys */
    const DEFAULT_KEY_SEPARATOR = '::';

    /** @var string default separated to be used for compound keys */
    const CORRESPONDING_ENTITY_ID_KEY = '_corresponding_entity_id';

    /** @var array model properties */
    protected $model_properties = [];

    /** @var array list of committees, indexed by its ID */
    protected $committees = [];

    /** @var array list of persons, indexed by its ID */
    protected $persons = [];

    /** @var array list of committee memberships, indexed by its ID */
    protected $memberships = [];

    /** @var array list of addresses, indexed by its ID */
    protected $addresses = [];

    /** @var array list of phones, indexed by its ID */
    protected $phones = [];

    /** @var array list of emails, indexed by its ID */
    protected $emails = [];

    /** @var array list of urls, indexed by its ID */
    protected $urls = [];

    /**
     * Get a property from the given model
     *
     * @param string $property_name
     *
     * @return string|int|boolean
     */
    public function getProperty($property_name)
    {
        return $this->model_properties[$property_name] ?? null;
    }

    /**
     * Get a property from the given model
     *
     * @param string $property_name
     * @param string|int|boolean $value
     */
    public function setProperty(string $property_name, $value)
    {
        $this->model_properties[$property_name] = $value;
    }

    /**
     * Add a new person to the model
     *
     * Possible attributes:
     *  'id'           => committee ID
     *  'first_name'   => full name of the committee
     *  'last_name'    => short name of the committee
     *  'formal_title' => short handle, external ID
     *
     * @param array|CRM_Committees_Model_Person $data
     *    the committee data
     *
     * @return CRM_Committees_Model_Person
     */
    public function addPerson($data)
    {
        // todo: validation?
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Person($this, $data);
        }
        $this->persons[$data->getID()] = $data;
        return $data;
    }

    /**
     * Add a new committee as a data record.
     * Possible attributes:
     *  'id'         => committee ID
     *  'name'       => full name of the committee
     *  'name_short' => short name of the committee
     *  'handle'     => short handle, external ID
     *  'start_date' => date when the committee was/will be created
     *  'end_date'   => date when the committee was/will be terminated
     * @param array|CRM_Committees_Model_Committee $data
     *    the committee data
     *
     * @return CRM_Committees_Model_Committee
     */
    public function addCommittee($data)
    {
        // todo: validation
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Committee($this, $data);
        }
        // todo: validation?
        $this->committees[$data->getID()] = $data;
        return $data;
    }

    /**
     * Add a new address to the model
     *
     * Possible attributes:
     *  'id'              => address ID
     *  'street_address'  => street address
     *  'postal_code'     => postal code
     *  'city'            => city
     *
     * @param array|CRM_Committees_Model_Address $data
     */
    public function addAddress($data)
    {
        // todo: validation
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Address($this, $data);
        }
        // todo: validation?
        $this->addresses[$data->getID()] = $data;
        return $data;
    }

    /**
     * Add a new email address to the model
     *
     * Possible attributes:
     *  'id'              => email ID
     *  'contact_id'      => person or organisation ID
     *  'email'           => email address
     *  'type'            => email type
     *
     * @param array|CRM_Committees_Model_Email $data
     */
    public function addEmail($data)
    {
        // todo: validation
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Email($this, $data);
        }
        // todo: validation?
        $this->emails[$data->getID()] = $data;
        return $data;
    }

    /**
     * Add a new url to the model
     *
     * Possible attributes:
     *  'id'              => url ID
     *  'contact_id'      => person or organisation ID
     *  'url'             => the url
     *  'type'            => url type
     *
     * @param array|CRM_Committees_Model_Url $data
     */
    public function addUrl($data)
    {
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Url($this, $data);
        }
        $this->urls[$data->getID()] = $data;
        return $data;
    }

    /**
     * Add a new phone number to the model
     *
     * Possible attributes:
     *  'id'              => email ID
     *  'contact_id'      => person or organisation ID
     *  'phone'           => phone number
     *  'type'            => email type
     *
     * @param array|CRM_Committees_Model_Phone $data
     */
    public function addPhone($data)
    {
        // todo: validation
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Phone($this, $data);
        }
        // todo: validation?
        $this->phones[$data->getID()] = $data;
        return $data;
    }

    /**
     * Add a new committee membership as to the model
     *
     * Possible attributes:
     *
     *  'id'              => email ID
     *  'contact_id'      => person or organisation ID
     *  'committee_id'    => id of a committee
     *  'title'           => membership title
     *  'represents'      => Organisation name?
     *  'start_date'      => when did the membership start?
     *  'end_date'        => when did the membership end?
     *
     * @param array|CRM_Committees_Model_Membership $data
     */
    public function addCommitteeMembership($data)
    {
        // todo: validation
        if (is_array($data)) {
            $data = new CRM_Committees_Model_Membership($this, $data);
        }
        $data->validate();
        $this->memberships[$data->getID()] = $data;
        return $data;
    }


    /**
     * Get a membership with the given ID
     *
     * @param string $id
     *   the ID
     *
     * @return CRM_Committees_Model_Membership $data
     */
    public function getCommitteeMembership($id)
    {
        return $this->memberships[$id] ?? null;
    }

    /**
     * Get a committee with the given ID
     *
     * @param string $id
     *   the ID
     *
     * @return CRM_Committees_Model_Committee $data
     */
    public function getCommittee($id)
    {
        return $this->committees[$id] ?? null;
    }

    /**
     * Get a person with the given ID
     *
     * @param string $id
     *   the ID
     *
     * @return CRM_Committees_Model_Person $data
     */
    public function getPerson($id)
    {
        return $this->persons[$id] ?? null;
    }

    /**
     * Get a list of all committees
     *
     * @return array
     */
    public function getAllCommittees()
    {
        return $this->committees;
    }

    /**
     * Get a list of all committees
     *
     * @return array
     */
    public function getAllPersons()
    {
        return $this->persons;
    }

    /**
     * Get a list of all committees
     *
     * @return array
     */
    public function getAllMemberships()
    {
        return $this->memberships;
    }

    /**
     * Generate a list of all membership, indexed by person
     *
     * @return array
     */
    public function getAllMembershipsByPersonId()
    {
        $membership_by_person_id = [];
        foreach ($this->memberships as $membership) {
            /** @var CRM_Committees_Model_Membership $membership */
            $membership_by_person_id[$membership->getPerson()->getID()][] = $membership;
        }
        return $membership_by_person_id;
    }

    /**
     * Diff the memberships of this model against another i.e. identify the ones:
     *   that are new, that have been changed, that ore obsolete
     *
     * @param $model CRM_Committees_Model_Model
     *   the model to compare with
     *
     * @param array $ignore_attributes
     *   list of entity attributes to ignore
     *
     * @param array $id_properties
     *   list of entity attributes used to define equality
     *   default is ['committee_id', 'contact_id']
     *
     * @return array of arrays:
     *  [
     *      new entities (only in other model),
     *      entities changed (with additional attribute 'differing_attributes'),
     *      entities missing (only in this model)
     *  ]
     */
    public function diffMemberships(CRM_Committees_Model_Model $model, array $ignore_attributes = [], array $id_properties = null)
    {
        if (!$id_properties) {
            $id_properties = ['contact_id', 'committee_id', 'relationship_type_id'];
        }
        return $this->diffEntities($model, 'memberships', $id_properties, $ignore_attributes);
    }

    /**
     * Diff the emails of this model against another i.e. identify the ones:
     *   that are new, that have been changed, that ore obsolete
     *
     * @param $model CRM_Committees_Model_Model
     *   the model to compare with
     *
     * @param array $ignore_attributes
     *   list of entity attributes to ignore
     *
     * @param array $id_properties
     *   list of entity attributes used to define equality
     *   default is ['email', 'contact_id']
     *
     * @return array of arrays:
     *  [
     *      new entities (only in other model),
     *      entities changed (with additional attribute 'differing_attributes'),
     *      entities missing (only in this model)
     *  ]
     */
    public function diffEmails(CRM_Committees_Model_Model $model, array $ignore_attributes = [], array $id_properties = null)
    {
        if (!$id_properties) {
            $id_properties = ['email', 'contact_id'];
        }
        return $this->diffEntities($model, 'emails', $id_properties, $ignore_attributes);
    }

    /**
     * Diff the urls of this model against another i.e. identify the ones:
     *   that are new, that have been changed, that ore obsolete
     *
     * @param $model CRM_Committees_Model_Model
     *   the model to compare with
     *
     * @param array $ignore_attributes
     *   list of entity attributes to ignore
     *
     * @param array $id_properties
     *   list of entity attributes used to define equality
     *   default is ['url', 'contact_id']
     *
     * @return array of arrays:
     *  [
     *      new entities (only in other model),
     *      entities changed (with additional attribute 'differing_attributes'),
     *      entities missing (only in this model)
     *  ]
     */
    public function diffUrls(CRM_Committees_Model_Model $model, array $ignore_attributes = [], array $id_properties = null)
    {
        if (!$id_properties) {
            $id_properties = ['url', 'contact_id'];
        }
        return $this->diffEntities($model, 'urls', $id_properties, $ignore_attributes);
    }

    /**
     * Diff the phones of this model against another i.e. identify the ones:
     *   that are new, that have been changed, that ore obsolete
     *
     * @param $model CRM_Committees_Model_Model
     *   the model to compare with
     *
     * @param array $ignore_attributes
     *   list of entity attributes to ignore
     *
     * @param array $id_properties
     *   list of entity attributes used to define equality
     *   default is ['phone', 'contact_id']
     *
     * @return array of arrays:
     *  [
     *      new entities (only in other model),
     *      entities changed (with additional attribute 'differing_attributes'),
     *      entities missing (only in this model)
     *  ]
     */
    public function diffPhones(CRM_Committees_Model_Model $model, array $ignore_attributes = [], array $id_properties = null)
    {
        if (!$id_properties) {
            $id_properties = ['phone_numeric', 'contact_id'];
        }
        return $this->diffEntities($model, 'phones', $id_properties, $ignore_attributes);
    }

    /**
     * Diff the addresses of this model against another i.e. identify the ones:
     *   that are new, that have been changed, that ore obsolete
     *
     * @param $model CRM_Committees_Model_Model
     *   the model to compare with
     *
     * @param array $ignore_attributes
     *   list of entity attributes to ignore
     *
     * @param array $id_properties
     *   list of entity attributes used to define equality
     *   default is ['contact_id', 'postal_code', 'city', 'street_address']
     *
     * @return array of arrays:
     *  [
     *      new entities (only in other model),
     *      entities changed (with additional attribute 'differing_attributes'),
     *      entities missing (only in this model)
     *  ]
     */
    public function diffAddresses(CRM_Committees_Model_Model $model, array $ignore_attributes = [], array $id_properties = null)
    {
        if (!$id_properties) {
            $id_properties = ['contact_id', 'postal_code', 'city', 'street_address'];
        }
        return $this->diffEntities($model, 'addresses', $id_properties, $ignore_attributes);
    }

    /**
     * Diff the persons of this model against another i.e. identify the ones:
     *   that are new, that have been changed, that ore obsolete
     *
     * @param $model CRM_Committees_Model_Model
     *   the model to compare with
     *
     * @param array $ignore_attributes
     *   list of entity attributes to ignore
     *
     * @return array of arrays:
     *  [
     *      new entities (only in other model),
     *      entities changed (with additional attribute 'differing_attributes'),
     *      entities missing (only in this model)
     *  ]
     */
    public function diffPersons(CRM_Committees_Model_Model $model, array $ignore_attributes = [])
    {
        return $this->diffEntities($model, 'persons', ['id'], $ignore_attributes);
    }

    /**
     * Diff the memberships of this model against another i.e. identify the ones:
     *   that are new, that have been changed, that ore obsolete
     *
     * @param $model CRM_Committees_Model_Model
     *   the model to compare with
     *
     * @param array $ignore_attributes
     *   list of entity attributes to ignore
     *
     * @return array of arrays:
     *  [
     *      new entities (only in other model),
     *      entities changed (with additional attribute 'differing_attributes'),
     *      entities missing (only in this model)
     *  ]
     */
    public function diffCommittees(CRM_Committees_Model_Model $model, array $ignore_attributes = [])
    {
        return $this->diffEntities($model, 'committees', ['id'], $ignore_attributes);
    }

    /**
     * Get a list of all addresses
     *
     * @return array
     */
    public function getAllAddresses()
    {
        return $this->addresses;
    }

    /**
     * Get a list of all address by 'id'
     *
     * @return array
     *  'id' => [entities]
     */
    public function getEntitiesByID($entities, $id_field = 'id')
    {
        $result = [];
        foreach ($entities as $entity) {
            /** @var $entity CRM_Committees_Model_Entity */
            $key = $id_field == 'id' ? $entity->getID() : $entity->getAttribute($id_field);
            $result[$key][] = $entity;
        }
        return $result;
    }

    /**
     * Get a list of all emails
     *
     * @return array
     */
    public function getAllEmails()
    {
        return $this->emails;
    }

    /**
     * Get a list of all urls
     *
     * @return array
     */
    public function getAllUrls()
    {
        return $this->urls;
    }

    /**
     * Get a list of all phones
     *
     * @return array
     */
    public function getAllPhones()
    {
        return $this->phones;
    }

    /**
     * Join the data of the 'other_entities' into the 'entities' on the two fields
     *
     * @param array $entities
     *   the entities that should be extended, i.e. will be added
     *
     * @param array $other_entities
     *    the other entities where the data is taken from
     *
     * @param string $other_id_field
     *    the field the carries the ID to be joined
     *
     * @param string $entity_id_field
     *    the field the carries the other ID to be joined
     *
     * @param array $fields
     *    list of fields to be copied. default is all (except pre-existing ones)
     */
    public function join(&$entities, $other_entities, $other_id_field = 'contact_id', $entity_id_field = 'id', $fields = null)
    {
        // create an index of the other entities
        $other_entities_indexed = [];
        foreach ($other_entities as $other_entity)
        {
            /** @var CRM_Committees_Model_Entity $other_entity */
            $other_entity_link = $other_entity->getAttribute($other_id_field);
            if (isset($other_entities_indexed[$other_entity_link])) {
                throw new Exception("Key field {$other_id_field} is not unique");
            } else {
                $other_entities_indexed[$other_entity_link] = $other_entity;
            }
        }

        // now join the data
        foreach ($entities as &$entity) {
            /** @var CRM_Committees_Model_Entity $entity */
            $key = $entity->getAttribute($entity_id_field);
            if (!isset($key)) {
                throw new Exception("Key field {$entity_id_field} is empty");
            }

            if (isset($other_entities_indexed[$key])) {
                /** @var CRM_Committees_Model_Entity $other_entity */
                $other_entity = $other_entities_indexed[$key];
                $attributes = $fields ?? $other_entity->getFields();
                foreach ($attributes as $attribute) {
                    if ($attribute != $entity_id_field && $attribute != $other_id_field) {
                        $new_value = $other_entity->getAttribute($attribute);
                        if ($new_value === null) {
                            Civi::log()->log("Join on {$entity_id_field}:{$other_id_field} missing an entry for {$attribute}.");
                        } else {
                            $entity->setAttribute($attribute, $new_value);
                        }
                    }
                }
            }
        }
    }

    /**
     * Join all the address data to the persons via the contact_id attribute
     *
     * @throws \Exception
     */
    public function joinAddressesToPersons()
    {
        $this->join($this->persons, $this->addresses, 'contact_id', 'id');
    }

    /**
     * Join all the email data to the persons via the contact_id attribute
     *
     * @throws \Exception
     */
    public function joinEmailsToPersons()
    {
        $this->join($this->persons, $this->emails, 'contact_id', 'id', ['email']);
    }

    /**
     * Join all the phone data to the persons via the contact_id attribute
     *
     * @throws \Exception
     */
    public function joinPhonesToPersons()
    {
        $this->join($this->persons, $this->phones, 'contact_id', 'id', ['phone']);
    }

    /**
     * Generate an identifier for the given entity
     *
     * @param CRM_Committees_Model_Entity $entity
     *   the entity to generate the key for
     *
     * @param string|array $identifiers
     *   the identifier(s) used to generate the (private) key
     *
     * @param string $separator
     *   the separator to be used to separate the key components (if multiple)
     *
     * @return string
     *   the generated identifier
     */
    protected function renderIdentifier(CRM_Committees_Model_Entity $entity, $identifiers = 'id', string $separator = self::DEFAULT_KEY_SEPARATOR) : string
    {
        if (!is_array($identifiers)) $identifiers = [$identifiers];

        // generate key
        $key_components = [];
        foreach ($identifiers as $identifier_attribute) {
            $key_components[] = $entity->getAttribute($identifier_attribute);
        }
        return implode($separator, $key_components);
    }

    /**
     * Get a list of the identifiers indexed by the given identifier(s)
     *
     * @param array $entity_list
     *   list of CRM_Committees_Model_Entity objects
     *
     * @param string|array $identifier
     *   the identifier(s) used to generate the (private) key
     *
     * @param string $separator
     *   separator used to generate the key. Default is '::'
     *
     * @return array
     */
    protected function getIndexedEntities(array $entity_list, $identifiers = 'id', string $separator = self::DEFAULT_KEY_SEPARATOR) : array
    {
        $indexed_entities = [];
        foreach ($entity_list as $entity) {
            /** @var CRM_Committees_Model_Entity $entity */
            $key = $this->renderIdentifier($entity, $identifiers, $separator);
            $indexed_entities[$key] = $entity;
        }
        return $indexed_entities;
    }

    /**
     * Diff the entities of this model against another i.e. identify the ones:
     *   that are new -
     *
     * @param $other_model CRM_Committees_Model_Model
     *   the model to compare with
     *
     * @param string $entity_list_property
     *   name of the property of the model to hold the entities
     *
     * @param array $identifiers
     *   list of identifiers to identify the entity
     *
     * @param array $ignore_attributes
     *   list of entity attributes to ignore
     *
     * @return array of arrays:
     *  [
     *      new entities (only in other model),
     *      entities changed (with additional attribute 'differing_attributes'),
     *      entities missing (only in this model)
     *  ]
     */
    public function diffEntities(
        CRM_Committees_Model_Model $other_model,
        string $entity_list_property = 'persons',
        array $identifiers = ['id'],
        array $ignore_attributes = [])
    {
        $new_entities = [];
        $changed_entities = [];
        $missing_entities = [];

        // todo: validate $entity_list_property to prevent crashes or mischief
        $our_entities = $this->getIndexedEntities($this->$entity_list_property, $identifiers);
        $other_entities = $this->getIndexedEntities($other_model->$entity_list_property, $identifiers);

        // first go through the first list...
        foreach ($our_entities as $our_entity_key => $our_entity) {
            /** @var CRM_Committees_Model_Entity $our_entity */
            if (isset($other_entities[$our_entity_key])) {
                // there is another entity with the same id
                /** @var CRM_Committees_Model_Entity $other_entity */
                $other_entity = $other_entities[$our_entity_key];
                $other_entity_id = $other_entity->getID();
                $diff = $our_entity->diff($other_entity, $ignore_attributes);
                if (!empty($diff)) {
                    $our_entity->setAttribute('differing_attributes', implode(',', array_keys($diff)));
                    $our_entity->setAttribute('differing_attributes', implode(',', array_keys($diff)));
                    $our_entity->setAttribute(CRM_Committees_Model_Model::CORRESPONDING_ENTITY_ID_KEY, $other_entity->getID());
                    $changed_entities[] = $our_entity;
                }
                unset($other_entities[$our_entity_key]);
            } else {
                // this entity is missing in the other model
                $missing_entities[] = $our_entity;
            }
        }

        // then go through the (remaining) second list
        foreach ($other_entities as $other_entity_key => $other_entity) {
            /** @var CRM_Committees_Model_Entity $other_entity */
            $new_entities[] = $other_entity;
        }

        return [$new_entities, $changed_entities, $missing_entities];
    }

    /**
     * Remove the given entity from this model
     *
     * @param CRM_Committees_Model_Entity $entity
     */
    public function removeEntity(CRM_Committees_Model_Entity $entity)
    {
        if ($entity->getModel() !== $this) {
            throw new Exception("Entity belongs to another model.");
        }

        if ($entity instanceof CRM_Committees_Model_Person) {
            unset($this->persons[$entity->getID()]);
        } elseif ($entity instanceof CRM_Committees_Model_Address) {
            unset($this->addresses[$entity->getID()]);
        } elseif ($entity instanceof CRM_Committees_Model_Email) {
            unset($this->emails[$entity->getID()]);
        } elseif ($entity instanceof CRM_Committees_Model_Phone) {
            unset($this->phones[$entity->getID()]);
        } elseif ($entity instanceof CRM_Committees_Model_Committee) {
            unset($this->committees[$entity->getID()]);
        } elseif ($entity instanceof CRM_Committees_Model_Membership) {
            unset($this->memberships[$entity->getID()]);
        } elseif ($entity instanceof CRM_Committees_Model_Person) {
            unset($this->persons[$entity->getID()]);
            throw new Exception("removeEntity:Person incomplete, needs to affect depending entities as well");
        }
    }
}
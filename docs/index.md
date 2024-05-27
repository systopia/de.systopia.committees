# Committee Synchronisation (de.systopia.committees)

The focus of this extension is to facilitate the initial import and continuous update of the following data:
* Committees - e.g. parliaments
* Contacts, along with address, email, phone and website - e.g. members of parliament
* Committee memberships

In order to do this, the system consists of three parts:
1. Importer module - there will be different implementations available to import committee data from a variety of sources. The job of the importer is, to fill the model data structure with the information retrieved from the source
2. Model - the model is simply a linked, standardised, internal data structure containing the information extracted from the data source. This should be the only way to pass information from the importer to the syncer module.  
3. Syncer - this module will transfer the data contained in the model and apply it to the current CiviCRM. Ideally, it would check for differences between the model and the current data, and only applies changes.

# Getting Started

Install the extension, select an importer, and see if you can obtain the data as needed.
If so, select the desired syncer, upload the file and wait. You will currently not get any progress report, but a link
to a full log of the changes when it's done.

Make sure to read the documentation of the selected modules (if available) before you start the process.

**Important:** Be sure to create a full DB backup before using the importers, until you're
sure that your data, the importer and the syncer module do the "right thing" for you.

# Details

## The Model

The model consists of the following entities:
1. Committee
2. Person
3. Membership (Person -> Committee)
4. Email
5. Phone

Every entity has some basic attributes (usually based on the CiviCRM fields), but can also have free, additional attributes in order to pass further information to syncers that are able to "understand" those.   

### Importer Modules

* [Kürschner List](importer/kuerschner/index.md) (German political address broker)
* PersonalOffice Importer - proprietary, will be removed
* Session Importer - proprietary, will be removed

### Syncer Modules

* [Bundestag (Oxfam)](syncer/oxfam/index.md)
* PersonalOffice Syncer - proprietary, will be removed
* Session Syncer - proprietary, will be removed

## Final Remarks

1. *Yes*, in theory this framework could also be used to import/sync only contacts
   without any further structures, but there might be better tools around for this.
   On the other hand, updating memberships based on a complete and up-to-date list of members might be a good job for this framework.

2. The individual modules might have additional requirements or dependencies. Those should be listed
   in the documentation, but the module will also give you a warning if you want to run it and
   the requirements aren't met.

3. All specialised modules that don't make sense for another user should be
   provided by a separate extension using a Symfony hook - and should *not* be part of this extension.

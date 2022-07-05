# Import and Synchronisation of Committee Structures

## General Information

### Installation

We recommend to download the [latest release](https://github.com/systopia/de.systopia.committees/releases), and install it using [the official guidline](https://docs.civicrm.org/sysadmin/en/latest/customize/extensions/#disable-automatic-installations-of-extension).

Status of the automated tests of this version is:

[![CircleCI](https://circleci.com/gh/systopia/de.committees.contract.svg?style=svg)](https://circleci.com/gh/systopia/de.systopia.committees)


### Objective

The focus of this extension is to facilitate the initial import and continuous update of the following data:
* Committees - e.g. parliaments
* Contacts, along with address, email, phone and website - e.g. members of parliament
* Committee memberships

For that will find the importer in Contact menu with the "Import/Synchronise Committees" item. There you'll have to select:
1. an importer module suitable for your data source, e.g. the German Küschner list.
2. a syncer module that defines the way the data is represented in your CiviCRM instance.

If the existing modules don't really work for you, you can contact us for new/adjusted modules at [info@systopia.de](mailto:info@systopia.de)

## Getting Started

Install the extension, select an importer, and see if you can obtain the data as needed.
If so, select the desired syncer, upload the file and wait. You will currently not get any progress report, but a link
to a full log of the changes when it's done.

Make sure to read the documentation of the selected modules (if available) before you start the process.

**Important:** Be sure to create a full DB backup before using the importers, until you're
sure that your data, the importer and the syncer module do the "right thing" for you.

## Remarks

1. Yes, in theory this framework could also be used to just import/sync contacts
without any further structures, but there might be better tools around for this.
On the other hand, updating memberships based on a complete and up-to-date list of members might be a better fit.

2. The individual modules might have additional requirements or dependencies. Those should be listed
in the documentation, but the module will also give you a warning if you want to run it and
the requirements aren't met.

3. All specialised modules that don't make sense for another user should be
provided by a separate extension using a Symfony hook.

The extension is licensed under [AGPL-3.0](LICENSE.txt).

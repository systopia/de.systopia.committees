# Oxfam Bundestag Importer

This syncer will try to import the model in the following way:

* All contacts involved will be added to a group ("Lobby-Kontakte")
* All persons will have relationships to the parliament, parliamentary committee (Ausschuss) and party group (Fraktion). Different relationship types are created for the different roles.
* All persons will have a shared address with the parliament, and personal email, phone and website
* Remark: the module is developed so that the behaviour could be modified by inheriting the 
class and overriding and adjusting some functions. This way, you can quickly generate 
your own flavour of this approach. 
* Developed and tested with  [Oxfam's Kürschner Importer](../../importer/kuerschner/index.md)
Remark: The development of this importer has been funded by Oxfam Germany

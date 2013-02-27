Community Hub Client
====================

version 2.3, release 2 - 2013.02.26


Change Log
----------

* 2.3, release 2
  * New Feature: Added Immediate Upload mode
* 2.3, release 1
  * Improvement: Shows popup when the upload completed


Requirements
------------

Moodle 2.3 (not compatible with 2.2 or earlier)


Installation
------------

1. Click on Site administration > Plugins > Blocks > Hub Client
2. Click on "Manage Hub servers"
3. Enter a Hub server URL into the text input and click on Add button
4. Set up http://docs.moodle.org/23/en/Cron if you selected Cron upload mode

(Currently, Hub Client version 2.3 does not support Moodle standard Hubs, e.g. MOOCH,
 but supports MAJ Community Hubs)


How to upload
-------------

1. Create a user account on the Community Hub site first
2. Turn editing on in the course you want to upload to Community Hub.
3. Add a block "Hub Client"
4. Click on "Upload to Hub" and enter your Community Hub account if popup dialog is shown
5. Wait for a cron job on your server to be done (if you configured Hub Client to use cron mode)
6. After upload, click on "Edit metadata" to fill the metadata of your courseware


License
-------

GPL v3

.. include:: /Includes.rst.txt

.. highlight:: typoscript

.. _page-ts-config:

============
Page TSconfig
============

Frontend related (mail content)
===============================

This extension come with three static Page TSconfig files, which all can be found here:

.. code-block:: text

   EXT:mail/Configuration/TsConfig/Page

*  `ContentElement/All.tsconfig` removes all not supported fluid_styled_content elements from the newContentElement wizard of TYPO3.
*  `BackendLayouts/Mail.tsconfig` adds a very simple one-column backend layout for mail content to the system.
*  `TCADefaults.tsconfig` contain some default pages settings which does not (for me) make sense in context of mails.

Backend related (modules)
=========================

All this MAIL configuration properties must be set in the Page TSconfig field of the MAIL
sys-folder under the key :typoscript:`mod.web_modules.mail`.

Most of these properties may conveniently be set using the :ref:`MAIL configuration module <configure-default-settings>`.

The following properties set default values for corresponding properties of mails and can (mostly) changed in the
:ref:`settings step of the mail wizard <mail-wizard-settings>`.

.. only:: html

   .. contents:: Properties
      :depth: 1
      :local:

fromEmail
---------

.. confval:: fromEmail

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.fromEmail

   Default value for the 'From' or sender email address of mails. (Required)

   Note: This email address appears as the originating address or sender
   address in the mails received by the recipients.

fromName
--------

.. confval:: fromName

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.fromName

   Default value for the 'From' or sender name address of mails. (Required)

   Note: This name appears as the name of the author or sender in the mails
   received by the recipients.

replyToEmail
------------

.. confval:: replyToEmail

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.replyToEmail

   Default value for 'Reply To' email address.

   Note: This is the email address to which replies to mails are sent.
   If not specified, the 'fromEmail' is used.

replyToName
------------

.. confval:: replyToName

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.replyToName

   Default value for 'Reply To' name.

   Note: This is the name of the 'Reply To' email address.
   If not specified, the 'fromName' is used.

returnPath
----------

.. confval:: returnPath

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.returnPath

   Default return path email address.

   Note: This is the address to which non-deliverable mails will be
   returned to.

   Note: If you put in the marker ###XID###, it'll be substituted with
   the unique id of the mail recipient.

organisation
------------

.. confval:: organisation

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.organisation

   Name of the organization sending the mail.

priority
--------

.. confval:: priority

   :type: int+
   :Default: 3
   :Path: mod.web\_modules.mail.priority

   Default priority of direct mails.

   Possible values are:

   1 - High

   3 - Normal

   5 – Low

sendOptions
-----------

.. confval:: sendOptions

   :type: int+
   :Default: 3
   :Path: mod.web\_modules.mail.sendOptions

   Default value for the format of email content.

   If in doubt, set it to 3 (Plain and HTML). The recipients are normally
   able to select their preferences anyway.

   Possible values are:

   1 - Plain text only

   2 - HTML only

   3 - Plain and HTML

.. _pageTsconfig_includeMedia:

includeMedia
------------

.. confval:: includeMedia

   :type: boolean
   :Default: 0
   :Path: mod.web\_modules.mail.includeMedia

   If set, images will be embedded into the HTML mail content.

   Note: Sent messages will be much heavier to transport.

   Note: To prevent embedding of a specific image, add the attribute `data-do-not-embed`
   to the image tag. This can be useful for adding third party tracking.

   When this option is not set, images and media are included in HTML
   content by absolute reference (href) to their location on the site
   where they reside.

htmlParams
----------

.. confval:: htmlParams

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.htmlParams

   Default value for additional URL parameters used to fetch the HTML content from a TYPO3 page.

   Note: The specified parameters will be added to the URL used to fetch the HTML content of the
   mail from a TYPO3 page. If in doubt, leave it blank.

plainParams
-----------

.. confval:: plainParams

   :type: string
   :Default: &plain=1
   :Path: mod.web\_modules.mail.plainParams

   Default value for additional URL parameters used to fetch the plain text content from a TYPO3 page.

   Note: The specified parameters will be added to the URL used to fetch the plain text content of the mail from a TYPO3 page.

   The default `&plain=1` will be handled by the Markdown Middleware come with this extension. This middleware
   generates a markdown (text) version of a html page and keeps content boundaries needed to separate content
   blocks with specific categories.

encoding
--------

.. confval:: encoding

   :type: string
   :Default: quoted-printable
   :Path: mod.web\_modules.mail.encoding

   Content transfer encoding to use when sending mails.

   Possible values:

   quoted-printable

   base64

   8bit

charset
-------

.. confval:: charset

   :type: string
   :Default: utf-8
   :Path: mod.web\_modules.mail.charset

   Character set to use when sending mails.


quickMailEncoding
-----------------

.. confval:: quickMailEncoding

   :type: string
   :Default: quoted-printable
   :Path: mod.web\_modules.mail.quickMailEncoding

   Content transfer encoding to use when sending quick mails.

   Possible values:

   quoted-printable

   base64

   8bit

quickMailCharset
----------------

.. confval:: quickMailCharset

   :type: string
   :Default: utf-8
   :Path: mod.web\_modules.mail.quickMailCharset

   Default character set for mails built from external pages.

   Note: This is the character set used in mails when they are
   built from external pages and character set cannot be auto-detected.

redirect
--------

.. confval:: redirect

   :type: boolean
   :Default: 0
   :Path: mod.web\_modules.mail.redirect

   If set, links longer than 76 characters found in plain text content will be redirected.
   This is realized by creating protected TYPO3 redirect entries, which hold the long URL.
   Links in the mail will be replaced by URLs starting with /redirect-[md5hash].

   Note: This configuration determines how Quick Mails are handled and
   further sets the default value for mails from internal pages.

redirectAll
-----------

.. confval:: redirectAll

   :type: boolean
   :Default: 0
   :Path: mod.web\_modules.mail.redirectAll

   If set and redirect is set as well, all links in plain text content will be redirected, not only links longer than 76 characters.

clickTracking
-------------

.. confval:: clickTracking

   :type: boolean
   :Default: 0
   :Path: mod.web\_modules.mail.clickTracking

   Enables click tracking

clickTrackingMailTo
-------------------

.. confval:: clickTrackingMailTo

   :type: boolean
   :Default: 0
   :Path: mod.web\_modules.mail.clickTrackingMailTo

   Enables click tracking for mailto-links as well

.. _pageTsconfig_trackingPrivacy:

trackingPrivacy
---------------

.. confval:: trackingPrivacy

   :type: boolean
   :Default: 0
   :Path: mod.web\_modules.mail.trackingPrivacy

   Do not add recipient id to click tracking.

authCodeFields
--------------

.. confval:: authCodeFields

   :type: string
   :Default: uid
   :Path: mod.web\_modules.mail.authCodeFields

   Default list of fields to be used in the computation of the authentication code included in unsubscribe links
   and for click tracking of mails.

httpUsername
------------

.. confval:: httpUsername

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.httpUsername

   The username used to fetch the mail content, if mail content is protected by HTTP authentication.

   Note: The username is NOT sent in the mail!

   Note: If you do not specify a username and password and a newsletter
   page happens to be protected, an error will occur and no mail content
   will be fetched.

httpPassword
------------

.. confval:: httpPassword

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.httpPassword

   The password used to fetch the mail content, if mail content is protected by HTTP authentication.

   Note: The password is NOT sent in the mail!

   Note: If you do not specify a username and password and a newsletter
   page happens to be protected, an error will occur and no mail content
   will be fetched.

simulateUsergroup
-----------------

.. confval:: simulateUsergroup

   :type: integer
   :Default:
   :Path: mod.web\_modules.mail.simulateUsergroup

   If mail content is protected by Frontend user authentication, enter
   a user group that has access to the page.

   Note: If you do not specify a usergroup uid and the page has frontend
   user restrictions, an error will occur and no mail content will be
   fetched.

testMailGroupUids
-----------------

.. confval:: testMailGroupUids

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.testMailGroupUids

   List of UID numbers of test recipient groups.

   Before sending mails, you should test the mail content by sending test
   mails to one or more test recipients. The available recipient groups for
   testing are determined by this list of UID numbers. So first, find out
   the UID numbers of the recipient groups you wish to use for testing, then
   enter them here in a comma-separated list.

testTtAddressUids
-----------------

.. confval:: testTtAddressUids

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.testTtAddressUids

   List of UID numbers of test recipients.

   Before sending mails, you should test the mail content by sending test
   mails to one or more test recipients. The available recipients for
   testing are determined by this list of UID numbers. So first, find out
   the UID numbers of the recipients you wish to use for testing, then
   enter them here in a comma-separated list.

showContentTitle
----------------

.. confval:: showContentTitle

   :type: boolean
   :Default: 0
   :Path: mod.web\_modules.mail.showContentTitle

   If set to 1, then only content title, in which the link can be found, will be shown in the click statistics.

prependContentTitle
-------------------

.. confval:: prependContentTitle

   :type: boolean
   :Default: 0
   :Path: mod.web\_modules.mail.prependContentTitle

   If set to 1, then content title and the linked words will be shown

maxLabelLength
--------------

.. confval:: maxLabelLength

   :type: int
   :Default: 0
   :Path: mod.web\_modules.mail.maxLabelLength

   Maximum length of the clicked statistics label

sendPerCycle
------------

.. confval:: sendPerCycle

   :type: int
   :Default: 50
   :Path: mod.web\_modules.mail.sendPerCycle

   Send per circle for manual sending trigger via Queue module


queueLimit
----------

.. confval:: queueLimit

   :type: int
   :Default: 10
   :Path: mod.web\_modules.mail.queueLimit

   Number of mailings listed in queue module. If zero (0) all current and past mails will be visible.

refreshRate
-----------

.. confval:: refreshRate

   :type: int
   :Default: 5
   :Path: mod.web\_modules.mail.refreshRate

   Number of seconds between automatic refreshing of delivery progress bars. Set to 0, to stop automatic
   refreshing.

storage
-------

.. confval:: storage

   :type: int+
   :Default:
   :Path: mod.web\_modules.mail.importer.storage

   PID of the target SysFolder, in which the recipients will be imported.

removeExisting
--------------

.. confval:: removeExisting

   :type: boolean
   :Default: 0
   :Path: mod.web\_modules.mail.importer.removeExisting

   Remove all Addresses in the storage folder before importing.

firstFieldname
-------------

.. confval:: firstFieldname

   :type: boolean
   :Default: 0
   :Path: mod.web\_modules.mail.importer.firstFieldname

   First row of import file has field names.

delimiter
---------

.. confval:: delimiter

   :type: string
   :Default: comma
   :Path: mod.web\_modules.mail.importer.delimiter

   Field delimiter (data fields are separated by...) [comma, semicolon, colon, tab]

encapsulation
-------------

.. confval:: encapsulation

   :type: string
   :Default: doubleQuote
   :Path: mod.web\_modules.mail.importer.encapsulation

   Field encapsulation character (data fields are encapsulated with...) [doubleQuote, singleQuote]

validEmail
----------

.. confval:: validEmail

   :type: bool
   :Default: 0
   :Path: mod.web\_modules.mail.importer.validEmail

   Only update/import valid emails from csv data.

removeDublette
--------------

.. confval:: removeDublette

   :type: bool
   :Default: 0
   :Path: mod.web\_modules.mail.importer.removeDublette

   Filter email dublettes from csv data. If a dublette is found, only the first entry is imported.

updateUnique
------------

.. confval:: updateUnique

   :type: bool
   :Default: 0
   :Path: mod.web\_modules.mail.importer.updateUnique

   Update existing user, instead renaming the new user.

recordUnique
------------

.. confval:: recordUnique

   :type: string
   :Default:
   :Path: mod.web\_modules.mail.importer.recordUnique

   Specify the field which determines the uniqueness of imported users. [email, name]

inputDisable
------------

.. confval:: inputDisable

   :type: boolean
   :Default: 0
   :Path: mod.web\_modules.mail.importer.inputDisable

   Disable all of above input field, so that no user can change it.

resultOrder
-----------

.. confval:: resultOrder

   :type: string
   :Default: new, update, invalidEmail, double
   :Path: mod.web\_modules.mail.importer.resultOrder

   Set the order of import result. Keywords separated with comma. [new, update, invalidEmail, double]

Restrict categories example
===========================

Here is an Page TSconfig example of how to restrict a list of categories to a specific parent category (has uid 1 in this example):

.. code-block:: typoscript

   TCEFORM.tt_content.categories.config.treeConfig.startingPoints = 1
   TCEFORM.tt_content.categories.config.treeConfig.appearance.nonSelectableLevels = 0
   TCEFORM.tt_address.categories.config.treeConfig.startingPoints = 1
   TCEFORM.tt_address.categories.config.treeConfig.appearance.nonSelectableLevels = 0
   TCEFORM.fe_users.categories.config.treeConfig.startingPoints = 1
   TCEFORM.fe_users.categories.config.treeConfig.appearance.nonSelectableLevels = 0
   TCEFORM.tx_mail_domain_model_group.categories.config.treeConfig.startingPoints = 1
   TCEFORM.tx_mail_domain_model_group.categories.config.treeConfig.appearance.nonSelectableLevels = 0

This config placed in the Page TSconfig field of the MAIL sys-folder page, will reduce all categories shown in tt_content, tt_address, fe_users and for simple list recipient groups living inside the MAIL sys-folder to the parent category with the uid 1.

Beside of this, the :typoscript:`nonSelectableLevels = 0` lines prevent the parent category itself to be selectable.


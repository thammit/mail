.. include:: /Includes.rst.txt

.. highlight:: typoscript

.. _user-ts-config:

=============
User TSconfig
=============

The following properties may be used in user TSConfig (BE user or BE usergroups) to configure parts of the different
MAIL modules.

hideTabs
--------

.. confval:: hideTabs

   :type: string
   :Default:
   :Path: tx_mail.hideTabs

   Comma separated list of not needed mail sources in the mail wizard.

   Available value:

   *  internal: hide the internal page option

   *  external: hide the external page option

   *  quickmail: hide the quick mail option

   *  draft: hide the draft mail option

defaultTab
----------

.. confval:: defaultTab

   :type: string
   :Default: draft
   :Path: tx_mail.defaultTab

   Possible options: internal, external, quickmail, draft

hideConfiguration
-----------------

.. confval:: hideConfiguration

   :type: bool
   :Default: 0
   :Path: tx_mail.hideConfiguration

   Hide configuration button in mail wizard and queue module.

.. _user-ts-config-hideEditAllSettingsButton:

hideEditAllSettingsButton
-------------------------

.. confval:: hideEditAllSettingsButton

   :type: bool
   :Default: 0
   :Path: tx_mail.hideEditAllSettingsButton

   Hide edit all settings button in mail wizard settings step.

.. _user-ts-config-settingsWithoutTabs:

settingsWithoutTabs
-------------------

.. confval:: settingsWithoutTabs

   :type: bool
   :Default: 0
   :Path: tx_mail.settingsWithoutTabs

   Show mail settings in wizard step and report without tabs.

.. _user-ts-config-settings-general:

settings.general
----------------

.. confval:: settings.general

   :type: string
   :Default: subject,fromEmail,fromName,organisation,attachment
   :Path: tx_mail.settings.general

   Comma separated list of general fields which should be visible in the mail wizard settings step and report.

.. _user-ts-config-settings-headers:

settings.headers
----------------

.. confval:: settings.headers

   :type: string
   :Default: replyToEmail,replyToName,returnPath,priority
   :Path: tx_mail.settings.headers

   Comma separated list of header fields which should be visible in the mail wizard settings step and report.

.. _user-ts-config-settings-content:

settings.content
----------------

.. confval:: settings.content

   :type: string
   :Default: sendOptions,includeMedia,redirect,redirectAll,authCodeFields
   :Path: tx_mail.settings.content

   Comma separated list of content fields which should be visible in the mail wizard settings step and report.

.. _user-ts-config-settings-source:

settings.source
---------------

.. confval:: settings.source

   :type: string
   :Default: type,renderedSize,page,sysLanguageUid,plainParams,htmlParams
   :Path: tx_mail.settings.source

   Comma separated list of source fields which should be visible in the mail wizard settings step and report.

.. _user-ts-config-readOnlySettings:

readOnlySettings
----------------

.. confval:: readOnlySettings

   :type: string
   :Default: type,renderedSize
   :Path: tx_mail.readOnlySettings

   Comma separated list of fields which should be read only in the mail wizard settings step.
   Attention: this is only a visual restriction! If the user has the rights to change the corresponding
   fields of the tx_mail_domain_model_mail table he/she will be able to do this by clicking the
   "edit complete record" button! To prevent this, you have to restrict the fields in the usergroup
   settings (exclude fields) as well.

hideCategoryStep
----------------

.. confval:: hideCategoryStep

   :type: bool
   :Default: 0
   :Path: tx_mail.hideCategoryStep

   Hide the category mail wizard step.

hideManualSendingButton
-----------------------

.. confval:: hideManualSendingButton

   :type: bool
   :Default: 0
   :Path: tx_mail.hideManualSendingButton

   Hide manual sending button in queue module.

hideDeleteRunningSendingButton
------------------------------

.. confval:: hideDeleteRunningSendingButton

   :type: bool
   :Default: 0
   :Path: tx_mail.hideDeleteRunningSendingButton

   Hide delete running sending button in queue module.

hideDeleteReportButton
----------------------

.. confval:: hideDeleteReportButton

   :type: bool
   :Default: 0
   :Path: tx_mail.hideDeleteReportButton

   Hide delete report button in report module.

.. _user-ts-config-mail-module-page-id:

mailModulePageId
----------------

.. confval:: mailModulePageId

   :type: int
   :Default:
   :Path: tx_mail.mailModulePageId

   If extension configuration parameter :ref:`hideNavigation <extension-configuration-hide-navigation>`
   is set to 1 or :ref:`mailModulePageId` <extension-configuration-mail-module-page-id> is set to a mail
   module page, this value can be set to a (different) mail module page id. This way it is possible to
   show a user individual mail module.

.. _user-ts-config-mail-module-page-ids:

mailModulePageIds
-----------------

.. confval:: mailModulePageIds

   :type: string
   :Default:
   :Path: tx_mail.mailModulePageIds

   Reduces the page tree of the mail modules to a comma separated list of page uids.
   If set to "auto", the list will be automatically taken from the pages database table,
   based on the selected module (see :ref:`here <add-mail-sysfolder>`).
   This value overrides the :ref:`extension configuration <extension-configuration-mail-module-page-ids>`
   with the same name.

   This setting is ignored if mailModulePageId or hideNavigation is set, because this will result in
   no page tree at all!

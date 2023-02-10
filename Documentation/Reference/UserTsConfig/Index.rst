.. include:: /Includes.rst.txt

.. highlight:: typoscript

.. _user-ts-config:

============
User TSconfig
============

The following properties may be used in the User TSConfig (BE user or BE usergroups) to disable some options in the MAIL wizard.

hideTabs
--------

.. confval:: hideTabs

   :type: string
   :Default:
   :Path: tx_mail.hideTabs

   Hide the options of mail sources in the first step. To hide more than one options, you can separate the values with comma.

   Available value:

   *  internal: hide the internal page option

   *  external: hide the external page option

   *  quickmail: hide the quick mail option

   *  draft: hide the draft mail option

hideCategoryStep
----------------

.. confval:: hideCategoryStep

   :type: bool
   :Default: 0
   :Path: tx_mail.hideCategoryStep

   Hide category step.

defaultTab
----------

.. confval:: defaultTab

   :type: string
   :Default: draft
   :Path: tx_mail.defaultTab

   One of the keywords from hideTabs. If set, the chosen tab will be draft by default.

hideConfiguration
-----------------

.. confval:: hideConfiguration

   :type: bool
   :Default: 0
   :Path: tx_mail.hideConfiguration

   Hide configuration button in mail wizard and queue module

hideManualSending
-----------------

.. confval:: hideManualSending

   :type: bool
   :Default: 0
   :Path: tx_mail.hideManualSending

   Hide manual sending button in queue module

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

.. _user-ts-config-mail-module-page-id:

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

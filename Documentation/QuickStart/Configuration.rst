.. include:: /Includes.rst.txt

.. _quick-configuration:

===================
Quick configuration
===================

..  rst-class:: bignums

.. _import-recipient-sources:

#. Add recipient sources

   MAIL needs a yaml configuration for recipient sources. It comes with a ready
   configuration for frontend user groups (fe_groups), frontend user (fe_users)
   and addresses (tt_address).

   To add them all, just import the included yaml into your site configuration:

   .. code-block:: yaml

       imports:
          - { resource: "EXT:mail/Configuration/Site/RecipientSources.yaml" }

   ..  note::
      If no entries with the key prefix mail.recipientSources found in your site
      configuration, the default configuration from the upper path will be used.

   Read more about how to add your own recipient sources in the
   :ref:`Site configuration reference <site-configuration-recipient-sources>`.

   .. _add-mail-sysfolder:

#. Add a new sys-folder inside (!) of your page tree (not on page 0!)

   This folder will be used to store the MAIL pages later. Remember the uid of
   this new sys-folder page, you may need it in a later step.

   Open the settings of the new created sys-folder page, go to tab :guilabel:`Behaviour`
   and select :guilabel:`MAIL Module` under :guilabel:`Contains plugin`.

   .. include:: /Images/MailPageModule.rst.txt

   .. _add-static-page-ts-config-files:

#. Add static Page TSconfig files

   Switch to tab :guilabel:`Resources` and add this three static :guilabel:`Page TSconfig` entries:

   *  :guilabel:`MAIL: Remove not supported content element (mail)`
   *  :guilabel:`MAIL: Add simple mail backend layout (mail)`
   *  :guilabel:`MAIL: Default settings for mail pages (mail)`

   .. include:: /Images/MailPageTSconfig.rst.txt


   Press the good old floppy disc icon to save.

   After saving, a new backend layout :guilabel:`MAIL` should be available under the :guilabel:`Appearance` tab.

   .. _select-mail-backend-layout:

#. Select MAIL backend layout

   Choose it for this page and also for the subpages.

   .. include:: /Images/MailBackendLayouts.rst.txt


   .. _include-typoscript-templates:

#. Include TypoScript templates

   To use the included table based content elements, a corresponding TypoScript
   is provided by this extension.

   Go module :guilabel:`Web > Template` and chose your MAIL sys-folder. If not
   already done, create an extension template. Switch to view :guilabel:`Info/Modify`
   and click on :guilabel:`Edit the whole template record`.

   Switch to tab :guilabel:`Option` and check :guilabel:`Constants` and :guilabel:`Setup`
   to remove TypoScript settings from TypoScript records up in root-line.

   .. include:: /Images/MailRemoveConstantsSetup.rst.txt

   Switch to tab :guilabel:`Includes` and add the following templates from the list
   to the right:

   *  :guilabel:`Fluid Content Elements (fluid_styled_content)`
   *  :guilabel:`MAIL (mail)`

   .. include:: /Images/MailIncludeTypoScriptTemplates.rst.txt

   Read more about possible configurations via TypoScript in the
   :ref:`Reference <typoscript-settings>` section.

   .. _configure-default-settings:

#. Configure default settings

   MAIL brings – similar to EXT:direct_mail – an easy way to adjust some default settings,
   used by the mail creation wizard.

   You find it by clicking on the cog button (Configuration) on the upper right corner of the
   MAIL wizard module.

   .. include:: /Images/MailDefaultSettingsButton.rst.txt

   The input fields are divided into several groups, which can be reached by clicking on their titles.

   .. include:: /Images/MailDefaultSettings.rst.txt

   After filling all fields with your data, press SAVE to store.

..  tip::
   All configurations done here will be stored in the :guilabel:`Page TSconfig` field of the
   currently selected page. This way, it is possible to override settings in a deeper page tree level.


Further reading
===============

*  :ref:`Global extension configuration <extension-configuration>`
*  :ref:`Site configuration <site-configuration>` (Recipient sources and mail transport settings)
*  :ref:`TypoScript configuration <typoscript-page-view-settings>`
*  :ref:`Page TSconfig configuration <page-ts-config>`

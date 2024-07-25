.. include:: /Includes.rst.txt

.. _extension-configuration:

=======================
Extension Configuration
=======================

Some general settings can be configured in the Extension Configuration.

#. Go to :guilabel:`Admin Tools > Settings > Extension Configuration`
#. Choose :guilabel:`mail`

The settings are divided into several tabs and described here in detail:

.. only:: html

   .. contents:: Properties
        :local:
        :depth: 2

Mail
====

.. _extension-configuration-default-recipient-fields:

.. confval:: defaultRecipientFields

   :type: string
   :Default: uid, salutation, name, title, email, phone, www, address, company, city, zip, country, fax, firstname, first_name, last_name

   Comma separated list of default recipient fields. Will be available by markers in a mail message. e.g. name as ###USER_name### or ###USER_NAME### filled with (uppercase) value of the user.

.. _extension-configuration-additional-recipient-fields:

.. confval:: additionalRecipientFields

   :type: string
   :Default:

   Comma separated list of additional fields that may be substituted in the mail messages.

.. _extension-configuration-mail-page-type-number:

.. confval:: mailPageTypeNumber

   :type: int
   :Default: 24

   MAIL page type number. Change this number only if another extension use the same.

.. _extension-configuration-mail-module-position:

.. confval:: mailModulePosition

   :type: string
   :Default: after:web

   MAIL modules position in the side navigation. If set to empty, the module will move to the end of all other modules

.. _extension-configuration-mail-module-page-id:

.. confval:: mailModulePageId

   :type: int
   :Default:

   MAIL module page id. If set, page tree navigation will be hidden in MAIL module. Here you can
   add the uid of the MAIL sys-folder if you only have one.

.. _extension-configuration-mail-module-page-ids:

.. confval:: mailModulePageIds

   :type: string
   :Default: auto

   MAIL module page id. Reduces the page tree of the mail modules to a comma separated list of page
   uids. If set to "auto" (default), the list will be automatically taken from
   the pages database table, based on the selected module. This value can be
   overwritten via :ref:`userTS <user-ts-config-mail-module-page-id>`.

Feature
=======

.. _extension-configuration-hide-navigation:

.. confval:: hideNavigation

   :type: bool
   :Default:

   Hide navigation: If set, page tree navigation will be hidden in mail module. User tsconfig parameter :ref:`tx_mail.mailModulePageId <user-ts-config-mail-module-page-id>` has to be set!

.. _extension-configuration-delete-unused-markers:

.. confval:: deleteUnusedMarkers

   :type: bool
   :Default: 1

   Delete unused markers in personalized mails. All markers/placeholder without corresponding recipient data will remove from personalized mails.

.. _extension-configuration-notification-job:

.. confval:: notificationJob

   :type: bool
   :Default: 1

   Enable notification mail. Allow MAIL to send notification about start and end of a mailing job.

.. _extension-configuration-deactivate-categories:

.. confval:: deactivateCategories

   :type: bool
   :Default: 0

   Deactivate categories. Attention: If set, ALL category fields inside tt_content,fe_users,tt_address,tx_mail_domain_model_group will be disabled!

.. _extension-configuration-use-http-to-fetch:

.. confval:: useHttpToFetch

   :type: bool
   :Default: 0

   Use http connection for fetching Newsletter-Content. Even if your TYPO3 Backend is in SSL-Mode, the URL for fetching the newsletter contents will be http

Direct Mail Migration
=====================

.. _extension-configuration-direct-mail-category-sys-category-mapping:

.. confval:: directMailCategorySysCategoryMapping

   :type: string
   :Default:

   Mapping sys_dmail_category -> sys_category.
   Used by update wizard to migrate Direct Mail records to MAIL records.
   Format: 1:2,2:3,3:4 maps Direct mail category 1 to sys_category 2 and Direct mail category 2 to sys_category 3 and so on. If categories not found or set, new categories will be added.

.. _extension-configuration-direct-mail-category-sys-category-parent-category:

.. confval:: directMailCategorySysCategoryParentCategory

   :type: int
   :Default:

   Parent sys_category used by sys_dmail_category -> sys_category migration.
   If the migrated categories should be attached to a parent sys_category.


.. include:: /Includes.rst.txt

.. _page-ts-config:

============
Page TSconfig
============

There are currently three of such kind, who all can be found here:

.. code-block:: text

   EXT:mail/Configuration/TsConfig/Page

*  `TCADefaults.tsconfig` contain some default pages settings which does not (for me) make sense in context of mails.
*  `ContentElement/All.tsconfig` removes all not supported fluid_styled_content elements from the newContentElement wizard of TYPO3.
*  `BackendLayouts/Mail.tsconfig` adds a very simple one-column backend layout for mail content to the system.

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

Beside of this, the `nonSelectableLevels = 0` lines prevent the parent category itself to be checkable.


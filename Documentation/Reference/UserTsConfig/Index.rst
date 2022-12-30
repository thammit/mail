.. include:: /Includes.rst.txt

.. highlight:: typoscript

.. _page-ts-config:

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

   *  open: hide the open mail option

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
   :Default: open
   :Path: tx_mail.defaultTab

   One of the keywords from hideTabs. If set, the chosen tab will be open by default.

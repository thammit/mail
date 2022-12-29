.. include:: /Includes.rst.txt

.. _quick-start:

===========
Quick start
===========

.. rst-class:: bignums-tip

#. Installation:

   .. code-block:: bash

      composer require mediaessenz/mail

   :ref:`Update database scheme and clear caches <quick-installation>`

#. Configuration:

   #.  :ref:`Add or import recipient sources configuration yaml into site configuration <import-recipient-sources>`
   #.  :ref:`Add a new MAIL sys-folder inside of your page tree <add-mail-sysfolder>`
   #.  :ref:`Add static Page TSconfig files <add-static-page-ts-config-files>`
   #.  :ref:`Select MAIL backend layout <select-mail-backend-layout>`
   #.  :ref:`Include TypoScript templates <include-typoscript-templates>`
   #.  :ref:`Configure default settings <configure-default-settings>`

#. Create recipient group:

   :ref:`Create recipient group <create-recipient-groups>`

#. Create first MAIL:

   #.  :ref:`Create MAIL page <create-mail-page>`
   #.  :ref:`Create MAIL content <create-mail-content>`

#. Create mailing:

   #.  :ref:`Choose source <mail-wizard-choose-source>`
   #.  :ref:`Settings <mail-wizard-settings>`
   #.  :ref:`Categories <mail-wizard-categories>`
   #.  :ref:`Test mail <mail-wizard-test-mail>`
   #.  :ref:`Schedule sending <mail-wizard-schedule-sending>`

#. Send mail manually:

   :ref:`Send mail manually <send-mail-manually>`

#. :ref:`Mail report <mail-report>`

   :ref:`Mail report <mail-report>`

.. toctree::
   :maxdepth: 5
   :titlesonly:
   :hidden:

   Installation
   Configuration
   RecipientGroups
   CreateMail
   CreateMailing
   SendMail
   MailReport

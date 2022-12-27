.. include:: /Includes.rst.txt

.. _quick-start:

===========
Quick start
===========

.. rst-class:: bignums-tip

#. Installation:

   .. code-block:: bash

      composer require mediaessenz/mail

   #.  :ref:`Update database scheme and clear caches <quick-installation>`

#. Configuration:

   #.  :ref:`Add or import recipient sources configuration yaml into site configuration <import-recipient-sources>`
   #.  :ref:`Add a new MAIL sys-folder inside of your page tree <add-mail-sysfolder>`
   #.  :ref:`Add a TypoScript template record to the MAIL sys-folder <add-typoscript-template>`
   #.  :ref:`Configure default settings <configure-default-settings>`

#. Create recipient group:

   #.  :ref:`Create recipient group <create-recipient-groups>`

#. Create first MAIL page:

   #.  :ref:`Create MAIL page <create-mail-page>`
   #.  :ref:`Create MAIL content <create-mail-content>`

#. Schedule personalized mail:

   #.  :ref:`Schedule personalized mail <schedule-personalized-mail>`

#. Send first personalized mail:

   #.  :ref:`Send personalized mail <send-personalized-mail>`

.. toctree::
   :maxdepth: 5
   :titlesonly:
   :hidden:

   Installation
   Configuration
   RecipientGroups
   CreateMail
   ScheduleMail
   SendMail

.. include:: /Includes.rst.txt

.. _quick-installation:

==================
Quick installation
==================

Currently only composer installation is supported.

.. code-block:: bash

   composer require mediaessenz/mail

Since TYPO3 11.5 the extension will be automatically installed.
You do not have to activate it manually.

Update the database scheme
--------------------------

Open your TYPO3 backend with :ref:`system maintainer <t3start:system-maintainer>`
permissions.

In the module menu to the left navigate to :guilabel:`Admin Tools > Maintenance`,
then click on :guilabel:`Analyze database` and create all.

.. include:: /Images/AnalyzeDatabase.rst.txt

Clear all caches
----------------

In the same module :guilabel:`Admin Tools > Maintenance` you can also
conveniently clear all caches by clicking the button :guilabel:`Flush cache`.

.. include:: /Images/FlushCache.rst.txt

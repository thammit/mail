.. include:: /Includes.rst.txt

.. _quick-installation:

==================
Quick installation
==================

To add this extension to your existing TYPO3 system, you have tree options:

#. Composer installation (recommended)

   .. code-block:: bash

      composer require mediaessenz/mail

#. Extension manager

   Open "Admin and maintenance tools" > "TYPO3 extension manager" and search for "mail".
   Install it by clicking on the install button.

#. Download

   Download it from here: https://extensions.typo3.org/extension/mail and use the
   extension manager to upload and install it.


Update the database scheme
--------------------------

After the first install, depending of the way (composer or other), it could be
necessary to update the database, to get ready for go.

Open your TYPO3 backend with :ref:`system maintainer <t3start:system-maintainer>`
permissions.

In the module menu to the left navigate to :guilabel:`Admin Tools > Maintenance`,
then click on :guilabel:`Analyze database` and create all.

.. include:: /Images/AnalyzeDatabase.rst.txt

Clear all caches
----------------

Clearing all caches after installing a new extension is always a good thing.

In the same module like before, you can also conveniently do this by clicking the
button :guilabel:`Flush cache`.

.. include:: /Images/FlushCache.rst.txt

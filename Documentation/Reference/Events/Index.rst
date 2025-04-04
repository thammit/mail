.. include:: /Includes.rst.txt

.. _events:

======
Events
======

AdditionalMailHeadersEvent
==========================
Adds possibility to modify mail headers

DeactivateRecipientsEvent
=========================
To deactivate returned mail recipients within the report module (beside tt_address and fe_users), it is necessary to register corresponding event
listeners.

See `MEDIAESSENZ\Mail\EventListener\DeactivateAddresses` and `DeactivateFeUsers` for example.

..  note::
    Need an entry in a `Services.yaml` as well. It looks similar to this:

    .. code-block:: yaml

        VENDOR\ExtensionKey\EventListener\DeactivateMyAddresses:
          tags:
            - name: 'event.listener'
              identifier: 'vendor/extensionkey/deactivate-my-addresses'

ManipulateCsvImportDataEvent
============================
Adds possibility to modify csv data during import them to tt_address records. This needs tt_address to be installed.

ManipulateMailRecipientsEvent
=============================
Adds possibility to manipulate the recipients list of a mailing right after finishing the mail wizard.

..  tip::
    Usage example (filtering out recipient duplicates):

   - https://gitlab.com/mediaessenz/additional-mail-recipients/-/blob/main/Classes/EventListener/FilterDuplicateRecipients.php
   - https://gitlab.com/mediaessenz/additional-mail-recipients/-/blob/main/Configuration/Services.yaml

   Attention: This example does not take into account that a duplicate recipient may have other categories.

ManipulateMarkersEvent
======================

See `MEDIAESSENZ\Mail\EventListener\AddUpperCaseMarkers` for example.

..  note::
    Need an entry in a `Services.yaml` as well. For the example it looks like this:

    .. code-block:: yaml

        MEDIAESSENZ\Mail\EventListener\AddUpperCaseMarkers:
          tags:
            - name: 'event.listener'
              identifier: ''mediaessenz/mail/add-upper-case-markers'


ManipulateRecipientEvent
========================
See `MEDIAESSENZ\Mail\EventListener\ManipulateAddressRecipient` and `MEDIAESSENZ\Mail\EventListener\ManipulateFrontendUserRecipient` for example.

..  note::
    Need an entry in a `Services.yaml` as well.

RecipientsRestrictionEvent
==========================
Adds possibility to restrict recipients from extbase model sources

ScheduledSendBegunEvent
=======================
Adds possibility to do something after scheduled sending begun

ScheduledSendFinishedEvent
==========================
Adds possibility to do something after scheduled sending finished


Need more?
----------
Please contact me

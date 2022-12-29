.. include:: /Includes.rst.txt

.. _events:

======
Events
======

ManipulateMarkersEvent
======================

See `MEDIAESSENZ\Mail\EventListener\AddUpperCaseMarkers` for example.

..  note::
    Need an entry in a `Services.yaml` as well. For the example it looks like this:

    .. code-block:: yaml

        MEDIAESSENZ\Mail\EventListener\AddUpperCaseMarkers:
          tags:
            - name: 'event.listener'
              identifier: 'addUpperCaseMarkers'


ManipulateRecipientEvent
========================
See `MEDIAESSENZ\Mail\EventListener\ManipulateAddressRecipient` and `MEDIAESSENZ\Mail\EventListener\ManipulateFrontendUserRecipient` for example.

..  note::
    Need an entry in a `Services.yaml` as well.

More to come ...
================

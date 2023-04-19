.. include:: /Includes.rst.txt

.. _site-configuration:

=================
Site Configuration
=================

This extension needs a existing site configuration to work, which should be standard nowadays.

There are two site configurations coming with this extension, both life inside the `EXT:mail/Configuration/Site` folder.

Transport.yaml
==============

This configuration should only be used as a copy base for your own mail transport settings.
The supported parameters are the same as definable in `$GLOBALS['TYPO3_CONF_VARS']['Mail']`.

Adding one or more of this setting inside your site configuration is only necessary if you like to override the mail transport setting for a specific site.
If a property is not set via site config, there corresponding system setting is used, defined in `$GLOBALS['TYPO3_CONF_VARS']['Mail']`.
The overrides do only affect the transport settings used by EXT:mail or its `MEDIAESSENZ\Mail\Mail\(Mailer|MailMessage)` classes.

.. _site-configuration-recipient-sources:

RecipientSources.yaml
=====================

To add all recipient sources come with MAIL (fe_groups, fe_users, tt_address) you has nothing to do.

MAIL imports the settings stored in this file:

.. code-block:: yaml

   imports:
      - { resource: "EXT:mail/Configuration/Site/RecipientSources.yaml" }

If you would like to add your own recipient sources, just add it to your site configuration under the key `mail.recipientSources`.

..  note::
   If you add your own configuration, you also have to add the fe_groups and fe_users config from the mentioned file.
   Otherwise MAIL will not work correctly.

Here is an example of how to add a table which has all necessary fields (at least uid, email, name, salutation, mail_html, mail_active):

.. code-block:: yaml

   mail:
      recipientSources:
         tx_extension_domain_model_address: #<- Table name
            title: 'LLL:EXT:extension/Resources/Private/Language/locallang.xlf:tx_extension_domain_model_address.title' #<- title of the recipient source
            icon: 'tcarecords-tx_extension_domain_model_address-default' #<- icon identifier

To simplify things a bit, it is also possible to ignore the mail_html and mail_active fields (they not need to exist at all).
To do this, just add `forceHtmlMail: true` or/and `ignoreMailActive: true` to your recipientSource configuration.

If a table doesn't contain the upper mentioned fields, or the fields have different names, it is also possible to use an extbase model to map fields to the need.
A site configuration doing this could look like this:

.. code-block:: yaml

   mail:
      recipientSources:
         tx_extension_domain_model_address: #<- Table name
            title: 'LLL:EXT:extension/Resources/Private/Language/locallang.xlf:tx_extension_domain_model_address.title' #<- title of the recipient source
            icon: 'tcarecords-tx_extension_domain_model_address-default' #<- icon identifier
            model: Vendor\Extension\Domain\Model\MailAddress #<- the extbase model

Beside of the site configuration a model definition and a mapping configuration is needed as well.
Check the included model `EXT:mail/Classes/Domain/Model/Address.php` and `EXT:mail/Configuration/Extbase/Persistence/Classes.php` for how this can look like.

The model needs to implement at least the included RecipientInterface.
If you like to use categories, to send specific parts of a mail only to a specific group of recipients, the included CategoryInterface has to be implemented as well.

Since this extension can handle simple tables and extbase models as well, and in the end its data only used to replace a simple placeholder like ###USER_name###, I decided to use simple arrays for transporting.

Because of this, every model needs to have a getEnhancedData method, which must return an array of all fields, which should serve as placeholder later on.
Btw.: The same method will be used by the csv-export inside the recipient group module.

..  note::
   Since using a domain model for a recipient source brings extbase into the game, working with large recipient groups can become a game of patience.

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

It is also possible to use a table more than once to create recipient sources with a subset of e.g. tt_address records.

.. code-block:: yaml

   mail:
      recipientSources:
         female_tt_address:
            table: 'tt_address'
            title: 'Female filtered tt_address records using table'
            icon: 'tcarecords-tt_address-default'
            queryRestrictions:
              - 'MEDIAESSENZ\AdditionalMailRecipients\Database\Query\Restriction\FemaleRestriction'
            csvExportFields: [uid,gender,name,first_name,middle_name,last_name,title,email,phone,www,address,company,city,zip,country,fax,categories,mail_salutation,mail_html,tstamp]

In this case the table name has to be defined as `table` property. To create a subset of tt_address records, one or more QueryRestrictions can be defined.

Such a QueryRestrictions could look like this:

.. code-block:: php

   <?php
   namespace MEDIAESSENZ\AdditionalMailRecipients\Database\Query\Restriction;

   use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
   use TYPO3\CMS\Core\Database\Query\Expression\ExpressionBuilder;
   use TYPO3\CMS\Core\Database\Query\Restriction\QueryRestrictionInterface;

   class FemaleRestriction implements QueryRestrictionInterface
   {
       public function buildExpression(array $queriedTables, ExpressionBuilder $expressionBuilder): CompositeExpression
       {
           $constraints = [];
           foreach ($queriedTables as $tableAlias => $tableName) {
               if ($tableName === 'tt_address') {
                   // attention: since gender is a string in tt_address the value has to be set in double quotes!
                   // normally this should be done with help of the $queryBuilder->createNamedParameter('f', Connection::PARAM_STR)), but here we do not have the query builder :-(
                   $constraints[] = $expressionBuilder->eq(
                       $tableAlias . '.' . 'gender',
                       '"f"'
                   );
               }
           }
           return $expressionBuilder->and(...$constraints);
       }
   }

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

It is also possible to create a subset of record from a domain model source.

Since MAIL doesn't use the "normal" repository classes of a model, this restriction has to be added as an event listener (RecipientsRestrictionEvent).

An example how this could look like, can be found here: https://gitlab.com/mediaessenz/additional-mail-recipients

The responsible files in the example extension are
 - `Configuration/Services.yaml` (line 16-19)
 - `Configuration/Site/RecipientSources.yaml` (line 12-19)
 - `Classes/EventListener/AddressModelRestriction.php`.

.. include:: /Includes.rst.txt

.. _create-mail-content:

===================
Create MAIL content
===================

Let's add a headline element first and enter this content inside the headline field:

..  code-block:: none

   ###USER_salutation###


This is a marker or placeholder which will later be replaced by the individual recipient data.

There are a lot of other placeholders available:

..  code-block:: none

   ###USER_uid###
   ###USER_salutation###
   ###USER_name###
   ###USER_title###
   ###USER_email###
   ###USER_phone###
   ###USER_www###
   ###USER_address###
   ###USER_company###
   ###USER_city###
   ###USER_zip###
   ###USER_country###
   ###USER_fax###
   ###USER_firstname###
   ###USER_first_name###
   ###USER_last_name###

Additional ALL as uppercase version:

..  code-block:: none

      ###USER_SALUTATION###
      ###USER_NAME###
      ...

This will result in the uppercase salutation, name, ... of the recipient later.

Inspired by EXT:direct_mail, there are also some special placeholders available:

..  code-block:: none

      ###MAIL_RECIPIENT_SOURCE###
      ###MAIL_ID###
      ###MAIL_AUTHCODE###


To keep things simple for now, only add another content element to the MAIL page,
e.g. a "text with image".

After adding some dummy text choosing an image, specify its position, save and close it.


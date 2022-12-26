.. include:: /Includes.rst.txt

.. _create-recipient-groups:

=======================
Create recipient groups
=======================

Since this extension is made to send personalized mails to groups of recipients, this groups has to be defined first.

MAIL comes with a lot of possibilities:
   - From pages
      - compare to EXT:direct_mail, MAIL is not limited to fe_groups, fe_users, tt_address and one custom table
      - It is possible to add as many tables you like, as long they have the needed fields or an extbase model which implements at least the RecipientInterface, defined in :guilabel:`Classes/Domain/Model/RecipientInterface.php`
      - Beside the recipient source (table) it is also possible to set a starting point where the records should be taken from
      - Categories can also be set to filter the list of recipients to only those how have the at least one of them assigned as well
   - Static list
      - Single records of all defined sources can be added – also fe_groups which will add all fe_users who have this groups assigned.
   - Plain list
      - A comma separated list of recipients (just mails or names and mails separated by a comma or semicolon)
      - compare to EXT:direct_mail, within MAIL you also can choose whether the group of users should receive html or just plain mails.
      - categories are also definable
   - From other recipient lists
   - To make things even more flexible, it is also possible to create a compilation of different other recipient groups.

Some EXT:direct_mail power users may miss the possibility to define queries as sending groups.
This feature is currently not available, and will maybe come with a future release. Sponsoring is highly welcome.

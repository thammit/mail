.. include:: /Includes.rst.txt

.. _whatDoesItDo:

================
What does it do?
================

MAIL is a TYPO3 backend module for sending personalized mails to different groups of recipients.

This can be done from an internal TYPO3 page, from an external URL or a simple text field.

The date time of sending can be scheduled, and it's done by a queue command controller which sends
the mails in small packages to prevent blacklisting.

With an easy yaml configuration, included in a site configuration, it is possible to add any database
table as recipient source as long all necessary fields are available.

Because this is (mostly) not the case, it is also possible to define an extbase model, where the
mapping can be done inside a `Configuration/Extbase/Persistence/Classes.php` file.

The included mail template and some basic content elements are based on the mail template system
of foundation: https://get.foundation/emails.html

Thanks to the same php sass parser used by bootstrap_package, it is possible to modify several
settings (colors, widths, paddings, margin) using TypoScript constants.

The extension is highly inspired from direct_mail, and was the base of a massive refactoring.
Kudos to all people who put there lifeblood and love in this project.

For all EXT:direct_mail users who wants to try out this extension:
A direct_mail -> mail migration script can be found in the upgrade wizard of the TYPO3 install tool.

# Mail for TYPO3



## What does it do?

Mail is a TYPO3 backend module for sending personalized mails to different groups of recipients.

This can be done from an internal TYPO3 page, from an external URL or a simple text field.

The date time of sending can be scheduled, and it's done by a queue command controller which sends the mails in small packages to prevent blacklisting.

With an easy yaml configuration included in a site configuration it is possible to add any database table as recipient source as long all necessary fields are available.

Because this is (mostly) not the case, it is also possible to define an extbase model, where the mapping can be done inside a `Configuration/Extbase/Persistence/Classes.php` file.

The included mail template and some basic content elements are based on the mail template system of foundation: https://get.foundation/emails.html

Thanks to the same php sass parser used by bootstrap_package, it is possible to modify several settings (colors, widths, paddings, margin) over typoscript constants.

The extension is highly inspired from direct_mail, and was the base of a massive refactoring. Kudos to all people who put there lifeblood and love in this project.

For all who wants to try out this extension:
A direct_mail -> mail migration script can be found in the upgrade wizard of the TYPO3 install tool.

## How to start?

### 1. Install the extension

Currently only composer installation is supported.

```bash
composer req mediaessenz/mail
```

### 2. Add or import recipient sources configuration yaml into your site configuration
```yaml
imports:
  - { resource: "EXT:mail/Configuration/Site/RecipientSources.yaml" }
```


### 3. Add a new sysfolder inside (!) of your page tree (not on page 0!)

This folder will be used to store the mail pages later. Remember the uid of this new page, it will be needed in a later step.

Open the settings of the new created sysfolder page and go to tab "Behaviour", and select "Mail Module" under "Contains plugin".
Next, switch to tab "Resources" and add this three static page TSconfig entries:
 - "Mail: Remove not supported content element (mail)"
 - "Mail: Add simple mail backend layout (mail)"
 - "Mail: Default settings for mail pages (mail)"

Press the good old floppy disc icon to save the data
After saving, a new backend layout "Mail" should be available under the "Appearance" tab.
Choose it for this page and also for the subpages.

## 4. Add a typoscript template record to the mail sysfolder

Under options activate the checkboxes to clear constant and setup from upper levels.
After this change to tab "Contains" and add this two static templates:
 - "Fluid Content Elements (fluid_styled_content)"
 - "Mail (mail)"

Press the good old floppy disc icon again to save the data

## 5. Configure default settings
Mail brings, like direct_mail, an own backend module to adjust some default settings used during creation of a new mailing.

To do this, click on the blue cog icon (Configuration) on the left side within the other mail modules.

Now, you have to choose the mail sysfolder, because the configuration will be stored in it.

The input fields are split in several groups, which can be reached by clicking on there title.

After filling all fields with your data, press save to store it as pageTS-Config in the page you selected before.

## 6. Add a recipient group
Since this extension is made to send personalized mails to groups of recipients, this groups has to be defined first.
Mail comes with a lot of possibilities:
 - From pages
   - compare to direct_mail, mail is not limited to fe_groups, fe_users, tt_address and one custom table
   - It is possible to add as many tables as needed, as long they have the needed fields or an extbase model which implements at least the RecipientInterface, defined in `Classes/Domain/Model/RecipientInterface.php`
   - Beside the recipient source (table) it is also possible to set a starting point where the records should be taken from
   - Categories can also be set to filter the list of recipients to only those how have the at least one of them assigned as well
 - Static list
   - Single records of all defined sources can be added – also fe_groups which will add all fe_users who have this groups assigned.
 - Plain list
   - A comma separated list of recipients (just mails or names and mails separated by a comma or semicolon)
   - compare to direct_mail, within mail you also can choose whether the group of users should receive html or just plain mails.
   - categories are also definable
 - From other recipient lists
   - To make things even more flexible, it is also possible to create a compilation of different other recipient groups.

Some direct_mail power users may miss the possibility to define queries as sending groups.
This feature is currently not available, and will maybe come with a future release. Sponsoring is highly welcome.

## 7. Add a mail page to the mail sysfolder
Mail brings an own page type (24, can be changed inside extension settings), which should appear as a new icon (letter within an envelope) above the page tree, beside the other page type icons.

To create a new mail, just drag and drop the mail page icon inside the new mail sysfolder created in step 3.

Now you can add content to the mail page using the normal page module.

As you may notice, only a few content elements are available, and some of them do not work yet.

Currently only headline, text, text with image, text with media, image and html are working.

Additionally (untested) it should be possible to add a news plugin.

The included content elements are inspired by EXT:luxletter. Thanks to in2code for making it public.

### 8. Send your first personalized mail
After setting up all previous steps, its time to send our first personalized mail.
To do so, we first add some content element to our just created mail page.
Let's add a headline element first and enter this content inside the headline field:
```
###USER_salutation###
```
This is a marker or placeholder which will later be replaced by the individual recipient data.
There are a lot of other placeholders available:
```
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
```
Additional ALL as uppercase version:
```
###USER_SALUTATION###
###USER_NAME###
...
```
This will result in the uppercase salutation, name, ... of the recipient later.

Inspired by direct_mail, there are also some special placeholders available:
```
###MAIL_RECIPIENT_SOURCE###
###MAIL_ID###
###MAIL_AUTHCODE###
```

Since we want to keep things simple for now, we only add another content element to the mail page, e.g. text with image.

After adding some dummy text choosing an image, specify its position, save and close it.

Now click the blue/white envelope icon of the mail wizard module.

Choose the mail sysfolder and move over to the main content area of this module, where the accordion for "Internal Page" should now show the previous created mail page.

With the two page preview buttons (page-eye-icon) you can get an idea how the mail will look like at the mail client of the recipient later.

To continue click the NEXT button to get to the settings page.

On this page you can check or change the settings of the mail.
Adding attachments is also possible.

If done, click NEXT again to get to the category settings.

Since it is possible to choose categories inside the different recipient sources (eg. fe_users, tt_address) it must also be possible to set categories inside the different content elements.

This is what can be done here.

During moving your mouse over a row of categories the corresponding content elements on the right side gets highlighted.

To see categories at this place, they must be defined before, off course.

Here is an Page TSconfig example of how to restrict a list of categories to a specific parent category (14):
```
TCEFORM.tt_content.categories.config.treeConfig.startingPoints = 14
TCEFORM.tt_content.categories.config.treeConfig.appearance.nonSelectableLevels = 0
TCEFORM.tt_address.categories.config.treeConfig.startingPoints = 14
TCEFORM.tt_address.categories.config.treeConfig.appearance.nonSelectableLevels = 0
TCEFORM.fe_users.categories.config.treeConfig.startingPoints = 14
TCEFORM.fe_users.categories.config.treeConfig.appearance.nonSelectableLevels = 0
TCEFORM.tx_mail_domain_model_group.categories.config.treeConfig.startingPoints = 14
TCEFORM.tx_mail_domain_model_group.categories.config.treeConfig.appearance.nonSelectableLevels = 0
```
This config placed in the Page TSconfig field of the mail sysfolder page, will reduce all categories shown in tt_content, tt_address, fe_users and for simple list recipient groups to the parent category 14.

Beside of this is prevents that the parent page itself is checkable (nonSelectableLevels = 0).

## Support
Tell people where they can go to for help. It can be any combination of an issue tracker, a chat room, an email address, etc.

## Roadmap
If you have ideas for releases in the future, it is a good idea to list them in the README.

## Contributing
State if you are open to contributions and what your requirements are for accepting them.

For people who want to make changes to your project, it's helpful to have some documentation on how to get started. Perhaps there is a script that they should run or some environment variables that they need to set. Make these steps explicit. These instructions could also be useful to your future self.

You can also document commands to lint the code or run tests. These steps help to ensure high code quality and reduce the likelihood that the changes inadvertently break something. Having instructions for running tests is especially helpful if it requires external setup, such as starting a Selenium server for testing in a browser.

## Authors and acknowledgment
Show your appreciation to those who have contributed to the project.

## License
GPL 2.0+

## Project status
If you have run out of energy or time for your project, put a note at the top of the README saying that development has slowed down or stopped completely. Someone may choose to fork your project or volunteer to step in as a maintainer or owner, allowing your project to keep going. You can also make an explicit request for maintainers.

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

This folder will be used to store the mail pages later. Remember the uid of this new sysfolder page, you may need it in a later step.

Open the settings of the new created sysfolder page and go to tab "Behaviour", and select "Mail Module" under "Contains plugin".
Next, switch to tab "Resources" and add this three static page TSconfig entries:
 - "Mail: Remove not supported content element (mail)"
 - "Mail: Add simple mail backend layout (mail)"
 - "Mail: Default settings for mail pages (mail)"

Press the good old floppy disc icon to save the data.

After saving, a new backend layout "Mail" should be available under the "Appearance" tab.
Choose it for this page and also for the subpages.

### 4. Add a typoscript template record to the mail sysfolder

Under options activate the checkboxes to clear constant and setup from upper levels.
After this change to tab "Contains" and add this two static templates:
 - "Fluid Content Elements (fluid_styled_content)"
 - "Mail (mail)"

Press the good old floppy disc icon again to save the data.

### 5. Configure default settings
Mail brings, like direct_mail, an own backend module to adjust some default settings used during creation of a new mailing.

To do this, click on the blue cog icon (Configuration) on the left side within the other mail modules.

Now, you have to choose the mail sysfolder, because the configuration will be stored in it.

The input fields are split in several groups, which can be reached by clicking on there title.

After filling all fields with your data, press save to store it as Page TSconfig in the page you selected before.

### 6. Add a recipient group
Since this extension is made to send personalized mails to groups of recipients, this groups has to be defined first.
Mail comes with a lot of possibilities:
 - From pages
   - compare to direct_mail, mail is not limited to fe_groups, fe_users, tt_address and one custom table
   - It is possible to add as many tables you like, as long they have the needed fields or an extbase model which implements at least the RecipientInterface, defined in `Classes/Domain/Model/RecipientInterface.php`
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

### 7. Add a mail page to the mail sysfolder
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

Here is an Page TSconfig example of how to restrict a list of categories to a specific parent category (has uid 1 in this example):
```
TCEFORM.tt_content.categories.config.treeConfig.startingPoints = 1
TCEFORM.tt_content.categories.config.treeConfig.appearance.nonSelectableLevels = 0
TCEFORM.tt_address.categories.config.treeConfig.startingPoints = 1
TCEFORM.tt_address.categories.config.treeConfig.appearance.nonSelectableLevels = 0
TCEFORM.fe_users.categories.config.treeConfig.startingPoints = 1
TCEFORM.fe_users.categories.config.treeConfig.appearance.nonSelectableLevels = 0
TCEFORM.tx_mail_domain_model_group.categories.config.treeConfig.startingPoints = 1
TCEFORM.tx_mail_domain_model_group.categories.config.treeConfig.appearance.nonSelectableLevels = 0
```
This config placed in the Page TSconfig field of the mail sysfolder page, will reduce all categories shown in tt_content, tt_address, fe_users and for simple list recipient groups living inside the mail sysfolder to the parent category with the uid 1.

Beside of this, the nonSelectableLevels = 0 lines prevent the parent category itself is checkable.

But for now you can skip this step by click on NEXT, which brings you to the fourth step: test mail

Here you can send a simple test mail to one or a list of mail addresses.
But beware: The generated mail will include ALL content elements regardless of their categories. User fields will NOT be substituted with data.

After pressing NEXT another time, you reached the last point of this wizard: Schedule sending

On this site you can choose one (or more) recipient groups and the date/time when distribution should start.

For our test, just choose the recipient group you created in step 6 of this manual and press FINISH.

Now the mail was added to the mail queue, which can be managed in its own backend module.
Watch out for the blue/white clock icon on the left side.

If you click that clock button, you should see your mailing on top now.

In the case you did not have configured the mail sending queue command controller yet inside the TYPO3 scheduler module, you can press the button "Start sending manually" for now.

This will send the mail immediately to the members of the recipient group you choose in the last step of the mail wizard.

Now go to the mail program of your trust, to receive the recently generated mail.

If you add links to internal or external pages inside the mail content and activate click tracking inside the mail configuration module, you are also able to see which links are clicked how many times.
To do this, move to the reports module (pie chart icon) and click on the title of the mailing.

Under the panel "Performance" you can see some metrics about the responses.
This numbers, especially "Unique responses (links clicked)" and "Total responses/Unique responses" are coming from the code developed from the direct_mail team.
I have no clue, how relevant they are and even show the right values.
If someone with marketing skills could give me feedback about it, I would really be happy.

Basically thats all to know from a editor view.

But there are some more things to know for integrators and developers ...

## Integration

### TypoScript Template

The static template of this extension can be found in `EXT:mail/Configuration/TypoScript/ContentElements/` folder,
and contains a constants file where you can change a lot of things.
Most of them are also available by the constants editor and self explaining.

Beside `modifications`, all constants under the key `plugin.mail.settings.scss` are sass variables and identical with the values used by foundation mail.
See https://get.foundation/emails/docs/ for more info.

Since this extension make use of the php package scssphp/scssphp it is not necessary to use a special build pipeline to create css out of scss.
Thanks to Benjamin Kott at this place for the inspiration!

As in EXT:bootstrap_package it is possible to add your own scss variables under this key an use it later inside of your mail template.
E.g. the constant `plugin.mail.settings.scss.my-own-color = #ff6600` will be available as scss variable $my-own-color in your own scss file.

In the constants you also can adjust some logo settings (src, width and alt), used in the html mail template found under `EXT:mail/Resources/Private/Templates/Mail/Html.html`

Furthermore, you can (of course) override all template, partial and layout paths of the mail template and the table based content elements as well.

### Page TSconfig

There are currently three of such kind, who all can be found here:
```
EXT:mail/Configuration/TsConfig/Page
```
`TCADefaults.tsconfig` contain some default pages settings which does not (for me) make sense in context of mails.

`ContentElement/All.tsconfig` removes all not supported content elements from the newContentElement wizard of TYPO3.

`BackendLayouts/Mail.tsconfig` adds a very simple one column backend layout for mail content to the system.

### Site Configuration

This extension needs a existing site configuration to work, which should be standard nowadays.

There are two site configurations coming with this extension, both life inside the `Configuration/Site` folder.

#### Transport.yaml
This configuration should only be used as a copy base for your own mail transport settings.
The supported parameters are the same as defined in `$GLOBALS['TYPO3_CONF_VARS']['Mail']`.

Adding one or more of this setting inside your site configuration is only necessary if you like to override the mail transport setting for a specific site.
If a property is not set via site config, there corresponding system setting is used, defined in `$GLOBALS['TYPO3_CONF_VARS']['Mail']`.
The overrides do only affect the transport settings used by EXT:mail or its `MEDIAESSENZ\Mail\Mail\(Mailer|MailMessage)` classes.

#### RecipientSources.yaml
To add all recipient sources come with EXT:mail (fe_groups, fe_users, tt_address) you just can import this file inside your site configuration like this:
```yaml
imports:
  - { resource: "EXT:mail/Configuration/Site/RecipientSources.yaml" }
```
To add your own recipient source, just add another entry under the key `mail.recipientSources`.
Here is an example of how to add a table which has all necessary fields (at least uid, email, name, salutation, mail_html, mail_active):
```yaml
mail:
  recipientSources:
    tx_extension_domain_model_address: #<- Table name
      title: 'LLL:EXT:extension/Resources/Private/Language/locallang.xlf:tx_extension_domain_model_address.title' #<- title of the recipient source
      icon: 'tcarecords-tx_extension_domain_model_address-default' #<- icon identifier
```
To simplify things a bit, it is also possible to ignore the mail_html and mail_active fields (they not need to exists at all).
To do this, just add `forceHtmlMail: true` or/and `ignoreMailActive: true` to your recipientSource configuration.

If a table doesn't contain the upper mentioned fields, or the fields have different names, it is also possible to use an extbase model to map fields to the need.
A site configuration to do this could look like this:
```yaml
mail:
  recipientSources:
    tx_extension_domain_model_address: #<- Table name
      title: 'LLL:EXT:extension/Resources/Private/Language/locallang.xlf:tx_extension_domain_model_address.title' #<- title of the recipient source
      icon: 'tcarecords-tx_extension_domain_model_address-default' #<- icon identifier
      model: Vendor\Extension\Domain\Model\MailAddress #<- the extbase model
```
Beside of the site configuration a model definition and a mapping configuration is needed as well.
Check the included model `EXT:mail/Classes/Domain/Model/Address.php` and `EXT:mail/Configuration/Extbase/Persistence/Classes.php` to see how this can look like.

The model needs to implement at least the included RecipientInterface.
If you like to use categories, to send specific parts of a mail only to a specific group of recipients, the included CategoryInterface has to be implement as well.

Since this extension can handle simple tables and extbase models as well, and in the end its data only used to replace a simple placeholder like ###USER_name###, I decided to use simple arrays for transporting.

Because of this, every model needs to have the method getEnhancedData, which should return an array of all fields, which should serve as placeholder later on.
Btw.: The same method will be used by the csv-export inside the recipient group module.

### Scheduler Task / Command Controller

This extension comes with tree different command controller, which all can be added as task inside the TYPO3 scheduler module.

#### MassMailingCommand (mail:mass-mailing: Add scheduled newsletter to sending queue)
This command is responsible for adding scheduled mailings to the sending queue.
There are two mandatory parameters:
 - site-identifier
 - send-per-cycle

The first one (site-identifier) is the identifier of the site configuration where mail can find its recipient sources configuration.
You can find it inside the TYPO3 sites module under the "General" tab.

The second (send-per-cycle) determine how many mails should be sent per cycle.

The time between a cycle can be defined in the "Frequency" field of a task.
e.g. `* * * * *` will run the task every minute, if you put `50` into the send-per-cycle field, 50 Mails per minute will be sent.

#### SpoolSendCommand (mail:spool:send: Process newsletter sending queue)
This command controller can be used to send mail in a queue using a site specific mail transport configuration.
See site configuration `Transport.yaml` under Integration up in this manuel.
It is not really needed by this extension, since the sending is already queued by the MassMailingCommand.
But if a developer wants to use the extended Mail-Classes `MEDIAESSENZ\Mail\Mail\(Mailer|MailMessage)` inside their own extension, it can be usefully.

#### AnalyzeBounceMailCommand (mail:analyse-bounce-mail: Analyze bounced newsletters from a configured mailbox.)
This command fetches returned mails from the mail account defined in the return-path field of the mail configuration module.
Depends on special headers, the reason of the return will be added to the report of the corresponding mail.

## Developer Stuff

This extension make use of several packages all found at packagist.org:
 - tedivm/fetch (https://github.com/tedious/Fetch)
 - league/html-to-markdown (https://github.com/thephpleague/html-to-markdown)
 - tburry/pquery (https://github.com/tburry/pquery)
 - pelago/emogrifier (https://github.com/MyIntervals/emogrifier)
 - scssphp/scssphp (https://github.com/scssphp/scssphp)

Kudos to all involved coders who put her love and energy in it. Without her, this extension would not exist.


Under the hood the extension make use of the pelago/emogrifier package, which converts all css to inline styles, which is unfortunately necessary for outlook and co.

Additionally, it uses the league/html-to-markdown package to convert an html mail to a plain text (markdown) version automatically, if a recipient don't like such modern stuff :-).

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

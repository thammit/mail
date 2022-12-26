.. include:: /Includes.rst.txt

.. _send-personalized-mail:

=========
Send mail
=========

With finishing the schedule step of the wizard, a mail is added to the MAIL queue, which has its own backend module.
Watch out for the blue/white clock icon on the left side.

.. include:: /Images/MailQueue.rst.txt

If you click that clock button, you should see your mailing on top now.

In case you did not have configured the MAIL sending queue command controller  inside the TYPO3 scheduler module yet,
you can press the button "Start sending manually" for now.

This will send the mail immediately to the members of the recipient group you choose in the last step of the MAIL wizard.

Now go to the mail program of your trust, to receive the recently generated mail.

If you add links to internal or external pages inside the mail content and activate click tracking inside the MAIL
configuration module, you are also able to see which links are clicked how many times.
To do this, move to the report's module (pie chart icon) and click on the title of the mailing.

Under the panel "Performance" you can see some metrics about the responses.
This numbers, especially "Unique responses (links clicked)" and "Total responses/Unique responses" are taken from the
code developed from the EXT:direct_mail team.
I have no clue, how relevant they are and even show the right values.
If someone with marketing skills could give me feedback about it, I would really be happy.

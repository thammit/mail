.. include:: /Includes.rst.txt

.. _mail-report:

===========
Mail Report
===========

If you have added links to internal or external pages inside the mail content and activate click tracking inside the MAIL
configuration module, you are also able to see which links are clicked how many times.
To do this, move to the report's module by clicking on the pie chart icon:

.. include:: /Images/MailReports.rst.txt

To get the report of a specific mail, click on the corresponding mail subject:

.. include:: /Images/MailReportDetails.rst.txt

Under the panel "Mail" you can see some basic stuff.

Under the panel "Performance" you can see some metrics about the responses.

This numbers, especially "Unique responses (links clicked)" and "Total responses/Unique responses" are taken from the
code developed from the EXT:direct_mail team.

I have no clue, how relevant they are and even show the right values.
If someone with marketing skills could give me feedback about it, I would really be happy.

The panel "Delivery failed" shows the different types of returned mails.

..  note::
    This data only will be filled if return-path for mails is set and AnalyseBounceMail-Command-Controller is configured correctly.
    :ref:`See Command Controller Reference <analyze-bounce-mail-command-controller>`

If returned mails found, a forth box will appear, to view, delete or disable the corresponding recipients.

..  note::
    This only works, if :guilabel:`Privacy click tracking` inside :guilabel:`Mail > Configuration > Links and click tracking` is deactivated.

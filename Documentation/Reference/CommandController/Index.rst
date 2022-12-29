.. include:: /Includes.rst.txt

.. _command-controller:

==================
Command Controller
==================

MAIL comes with tree different command controller, which all can be added as task inside the TYPO3 scheduler module.

.. _mass-mailing-command-controller:

MassMailingCommand
==================

This command is responsible for adding scheduled mailings to the sending queue.
There are two mandatory parameters:

*  site-identifier
*  send-per-cycle

The first one (site-identifier) is the identifier of the site configuration where MAIL can find its recipient sources configuration.
You can find it inside the TYPO3 sites module under the "General" tab.

The second (send-per-cycle) determine how many mails should be sent per cycle.

The time between a cycle can be defined in the "Frequency" field of a task.
e.g. `* * * * *` will run the task every minute, if you put `50` into the send-per-cycle field, 50 Mails per minute will be sent.

.. _analyze-bounce-mail-command-controller:

AnalyzeBounceMailCommand
========================

This command fetches returned mails from the mail account defined in the return-path field of the MAIL configuration module.
Depends on special headers, the reason of the return will be added to the report of the corresponding mail.

.. _spool-send-command-controller:

SpoolSendCommand
================

This command controller can be used to send mail in a queue.
It's basically the same coming with TYPO3, but it uses a site specific mail transport configuration.
See site configuration `Transport.yaml` under Integration up in this manuel.
To make this command controller work, it is also necessary to set `transport_spool_type` to `file` or `memory`. if the type was set to `file`, the `transport_spool_type` has to be set as well, e.g. to `upload/tx_mailspool/`.
This command controller is not really needed by this extension, since the sending is already queued by the MassMailingCommand.
But if a developer wants to use the extended Mail-Classes `MEDIAESSENZ\Mail\Mail\(Mailer|MailMessage)` inside their own extension, it can be usefully.


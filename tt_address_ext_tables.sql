CREATE TABLE tt_address
(
    mail_active     tinyint(1) unsigned DEFAULT '0' NOT NULL,
    mail_html       tinyint(1) unsigned DEFAULT '0' NOT NULL,
    mail_salutation varchar(255)        DEFAULT ''  NOT NULL,
    KEY mail (mail_active, email, mail_html)
);

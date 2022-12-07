CREATE TABLE tx_mail_domain_model_mail
(
    uid              int(11) unsigned                               NOT NULL auto_increment,
    pid              int(11) unsigned    DEFAULT '0'                NOT NULL,
    tstamp           int(11) unsigned    DEFAULT '0'                NOT NULL,
    deleted          tinyint(4) unsigned DEFAULT '0'                NOT NULL,
    sys_language_uid int(11)             DEFAULT '0'                NOT NULL,
    type             tinyint(4) unsigned DEFAULT '0'                NOT NULL,
    page             int(11) unsigned    DEFAULT '0'                NOT NULL,
    attachment       tinyblob,
    subject          varchar(120)        DEFAULT ''                 NOT NULL,
    from_email       varchar(80)         DEFAULT ''                 NOT NULL,
    from_name        varchar(80)         DEFAULT ''                 NOT NULL,
    reply_to_email   varchar(80)         DEFAULT ''                 NOT NULL,
    reply_to_name    varchar(80)         DEFAULT ''                 NOT NULL,
    organisation     varchar(80)         DEFAULT ''                 NOT NULL,
    priority         tinyint(4) unsigned DEFAULT '0'                NOT NULL,
    encoding         varchar(80)         DEFAULT 'quoted-printable' NOT NULL,
    charset          varchar(20)         DEFAULT 'iso-8859-1'       NOT NULL,
    send_options     tinyint(4) unsigned DEFAULT '0'                NOT NULL,
    include_media    tinyint(4) unsigned DEFAULT '0'                NOT NULL,
    html_params      varchar(80)         DEFAULT ''                 NOT NULL,
    plain_params     varchar(80)         DEFAULT ''                 NOT NULL,
    sent             tinyint(4) unsigned DEFAULT '0'                NOT NULL,
    rendered_size    int(11) unsigned    DEFAULT '0'                NOT NULL,
    message_id       varchar(100),
    html_content     mediumblob,
    preview_image    longblob,
    plain_content    mediumblob,
    html_links       text,
    plain_links      text,
    recipients       text,
    scheduled        int(10) unsigned    DEFAULT '0'                NOT NULL,
    scheduled_begin  int(10) unsigned    DEFAULT '0'                NOT NULL,
    scheduled_end    int(10) unsigned    DEFAULT '0'                NOT NULL,
    return_path      varchar(80)         DEFAULT ''                 NOT NULL,
    redirect         tinyint(4) unsigned DEFAULT '0'                NOT NULL,
    redirect_all     tinyint(4) unsigned DEFAULT '0'                NOT NULL,
    redirect_url     varchar(2048)       DEFAULT ''                 NOT NULL,
    auth_code_fields varchar(80)         DEFAULT ''                 NOT NULL,
    recipient_groups varchar(80)         DEFAULT ''                 NOT NULL,
    PRIMARY KEY (uid)
);

CREATE TABLE tx_mail_domain_model_group
(
    uid          int(11) unsigned                NOT NULL auto_increment,
    pid          int(11) unsigned    DEFAULT '0' NOT NULL,
    tstamp       int(11) unsigned    DEFAULT '0' NOT NULL,
    deleted      tinyint(4) unsigned DEFAULT '0' NOT NULL,
    type         tinyint(4) unsigned DEFAULT '0' NOT NULL,
    title        tinytext                        NOT NULL,
    description  text                            NOT NULL,
    static_list  int(11)             DEFAULT '0' NOT NULL,
    list         mediumblob,
    csv          tinyint(4)          DEFAULT '0' NOT NULL,
    pages        tinyblob,
    record_type  varchar(255)        DEFAULT ''  NOT NULL,
    record_types text                            NOT NULL,
    recursive    tinyint(4)          DEFAULT '0' NOT NULL,
    children     tinyblob,
    categories   int(11)             DEFAULT '0' NOT NULL,
    PRIMARY KEY (uid),
    KEY parent (pid)
);

#
# Table structure for table 'tx_mail_group_mm'
# needed since automatic generation is buggy
# see https://forge.typo3.org/issues/99035
#
CREATE TABLE tx_mail_group_mm
(
    uid_local   int(11) unsigned DEFAULT '0' NOT NULL,
    uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
    tablenames  varchar(50)      DEFAULT ''  NOT NULL,
    sorting     int(11) unsigned DEFAULT '0' NOT NULL,
    KEY uid_local (uid_local),
    KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_mail_domain_model_log
(
    uid             int(11) unsigned             NOT NULL auto_increment,
    mail            int(11) unsigned DEFAULT '0' NOT NULL,
    recipient_uid   int(11)          DEFAULT '0' NOT NULL,
    recipient_table varchar(255)     DEFAULT ''  NOT NULL,
    email           varchar(255)     DEFAULT ''  NOT NULL,
    tstamp          int(11) unsigned DEFAULT '0' NOT NULL,
    url             tinyblob                     NULL,
    parse_time      int(11) unsigned DEFAULT '0' NOT NULL,
    response_type   tinyint(4)       DEFAULT '0' NOT NULL,
    format_sent     tinyint(4)       DEFAULT '0' NOT NULL,
    url_id          tinyint(4)       DEFAULT '0' NOT NULL,
    return_content  mediumblob                   NULL,
    return_code     smallint(6)      DEFAULT '0' NOT NULL,
    PRIMARY KEY (uid),
    KEY recipient (recipient_uid, recipient_table, mail, response_type, uid),
    KEY mail (mail, response_type, recipient_table, recipient_uid)
);

CREATE TABLE fe_users
(
    mail_active tinyint(3) unsigned DEFAULT '0' NOT NULL,
    categories  int(10) unsigned    DEFAULT '0' NOT NULL,
    mail_html   tinyint(3) unsigned DEFAULT '0' NOT NULL,
    KEY mail (mail_active, mail_html)
);

CREATE TABLE tt_address
(
    mail_active tinyint(3) unsigned DEFAULT '0' NOT NULL,
    mail_html   tinyint(3) unsigned DEFAULT '0' NOT NULL,
    KEY mail (mail_active, mail_html)
);

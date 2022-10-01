CREATE TABLE tx_mail_domain_model_mail (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
    sys_language_uid int(11) DEFAULT '0' NOT NULL,
    type tinyint(4) unsigned DEFAULT '0' NOT NULL,
    page int(11) unsigned DEFAULT '0' NOT NULL,
    attachment tinyblob,
    subject varchar(120) DEFAULT '' NOT NULL,
    from_email varchar(80) DEFAULT '' NOT NULL,
    from_name varchar(80) DEFAULT '' NOT NULL,
    reply_to_email varchar(80) DEFAULT '' NOT NULL,
    reply_to_name varchar(80) DEFAULT '' NOT NULL,
    organisation varchar(80) DEFAULT '' NOT NULL,
    priority tinyint(4) unsigned DEFAULT '0' NOT NULL,
    encoding varchar(80) DEFAULT 'quoted-printable' NOT NULL,
    charset varchar(20) DEFAULT 'iso-8859-1' NOT NULL,
    send_options tinyint(4) unsigned DEFAULT '0' NOT NULL,
    include_media tinyint(4) unsigned DEFAULT '0' NOT NULL,
    flowed_format tinyint(4) unsigned DEFAULT '0' NOT NULL,
    html_params varchar(80) DEFAULT '' NOT NULL,
    plain_params varchar(80) DEFAULT '' NOT NULL,
    sent tinyint(4) unsigned DEFAULT '0' NOT NULL,
    size int(11) unsigned DEFAULT '0' NOT NULL,
    mail_content mediumblob,
    query_info mediumblob,
    scheduled int(10) unsigned DEFAULT '0' NOT NULL,
    scheduled_begin int(10) unsigned DEFAULT '0' NOT NULL,
    scheduled_end int(10) unsigned DEFAULT '0' NOT NULL,
    return_path varchar(80) DEFAULT '' NOT NULL,
    redirect tinyint(4) unsigned DEFAULT '0' NOT NULL,
    redirect_all tinyint(4) unsigned DEFAULT '0' NOT NULL,
    redirect_url varchar(2048) DEFAULT '' NOT NULL,
    auth_code_fields varchar(80) DEFAULT '' NOT NULL,
    recipient_groups varchar(80) DEFAULT '' NOT NULL,
    PRIMARY KEY (uid)
);

CREATE TABLE tx_mail_domain_model_group (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
    type tinyint(4) unsigned DEFAULT '0' NOT NULL,
    title tinytext NOT NULL,
    description text NOT NULL,
    query blob,
    static_list int(11) DEFAULT '0' NOT NULL,
    list mediumblob,
    csv tinyint(4) DEFAULT '0' NOT NULL,
    pages tinyblob,
    whichtables tinyint(4) DEFAULT '0' NOT NULL,
    recursive tinyint(4) DEFAULT '0' NOT NULL,
    mail_groups tinyblob,
    select_categories int(11) DEFAULT '0' NOT NULL,
    sys_language_uid int(11) DEFAULT '0' NOT NULL,
    PRIMARY KEY (uid),
    KEY parent (pid)
);

CREATE TABLE tx_mail_group_mm (
    uid_local int(11) unsigned DEFAULT '0' NOT NULL,
    uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
    tablenames varchar(30) DEFAULT '' NOT NULL,
    sorting int(11) unsigned DEFAULT '0' NOT NULL,
    KEY uid_local (uid_local),
    KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_mail_domain_model_log (
    uid int(11) unsigned NOT NULL auto_increment,
    mid int(11) unsigned DEFAULT '0' NOT NULL,
    rid varchar(11) DEFAULT '0' NOT NULL,
    email varchar(255) DEFAULT '' NOT NULL,
    rtbl char(1) DEFAULT '' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    url tinyblob NULL,
    size int(11) unsigned DEFAULT '0' NOT NULL,
    parsetime int(11) unsigned DEFAULT '0' NOT NULL,
    response_type tinyint(4) DEFAULT '0' NOT NULL,
    html_sent tinyint(4) DEFAULT '0' NOT NULL,
    url_id tinyint(4) DEFAULT '0' NOT NULL,
    return_content mediumblob NULL,
    return_code smallint(6) DEFAULT '0' NOT NULL,
    PRIMARY KEY (uid),
    KEY rid (rid,rtbl,mid,response_type,uid),
    KEY `mid` (mid,response_type,rtbl,rid)
);

CREATE TABLE tx_mail_domain_model_category (
    uid int(11) unsigned NOT NULL auto_increment,
    pid int(11) unsigned DEFAULT '0' NOT NULL,
    tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    crdate int(11) unsigned DEFAULT '0' NOT NULL,
    cruser_id int(11) unsigned DEFAULT '0' NOT NULL,
    sorting int(10) unsigned DEFAULT '0' NOT NULL,
    sys_language_uid int(11) DEFAULT '0' NOT NULL,
    l18n_parent int(11) DEFAULT '0' NOT NULL,
    l18n_diffsource mediumblob NOT NULL,
    deleted tinyint(4) unsigned DEFAULT '0' NOT NULL,
    hidden tinyint(4) unsigned DEFAULT '0' NOT NULL,
    category tinytext NOT NULL,
    old_cat_number char(2) DEFAULT '' NOT NULL,

    PRIMARY KEY (uid),
    KEY parent (pid)
);

CREATE TABLE tx_mail_group_category_mm (
    uid_local int(11) unsigned DEFAULT '0' NOT NULL,
    uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
    tablenames varchar(30) DEFAULT '' NOT NULL,
    sorting int(11) unsigned DEFAULT '0' NOT NULL,
    KEY uid_local (uid_local),
    KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_mail_feuser_category_mm (
    uid_local int(11) unsigned DEFAULT '0' NOT NULL,
    uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
    tablenames varchar(30) DEFAULT '' NOT NULL,
    sorting int(11) unsigned DEFAULT '0' NOT NULL,
    KEY uid_local (uid_local),
    KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_mail_ttaddress_category_mm (
    uid_local int(11) unsigned DEFAULT '0' NOT NULL,
    uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
    tablenames varchar(30) DEFAULT '' NOT NULL,
    sorting int(11) unsigned DEFAULT '0' NOT NULL,
    KEY uid_local (uid_local),
    KEY uid_foreign (uid_foreign)
);

CREATE TABLE tx_mail_ttcontent_category_mm (
    uid_local int(11) unsigned DEFAULT '0' NOT NULL,
    uid_foreign int(11) unsigned DEFAULT '0' NOT NULL,
    tablenames varchar(30) DEFAULT '' NOT NULL,
    sorting int(11) unsigned DEFAULT '0' NOT NULL,
    KEY uid_local (uid_local),
    KEY uid_foreign (uid_foreign)
);

CREATE TABLE cache_tx_mail_stat (
    mid int(11) DEFAULT '0' NOT NULL,
    rid varchar(11) DEFAULT '0' NOT NULL,
    rtbl char(1) DEFAULT '' NOT NULL,
    pings tinyint(3) unsigned DEFAULT '0' NOT NULL,
    plain_links tinyint(3) unsigned DEFAULT '0' NOT NULL,
    html_links tinyint(3) unsigned DEFAULT '0' NOT NULL,
    links tinyint(3) unsigned DEFAULT '0' NOT NULL,
    recieved_html tinyint(3) unsigned DEFAULT '0' NOT NULL,
    recieved_plain tinyint(3) unsigned DEFAULT '0' NOT NULL,
    size int(11) DEFAULT '0' NOT NULL,
    tstamp int(11) DEFAULT '0' NOT NULL,
    pings_first int(11) DEFAULT '0' NOT NULL,
    pings_last int(11) DEFAULT '0' NOT NULL,
    html_links_first int(11) DEFAULT '0' NOT NULL,
    html_links_last int(11) DEFAULT '0' NOT NULL,
    plain_links_first int(11) DEFAULT '0' NOT NULL,
    plain_links_last int(11) DEFAULT '0' NOT NULL,
    links_first int(11) DEFAULT '0' NOT NULL,
    links_last int(11) DEFAULT '0' NOT NULL,
    response_first int(11) DEFAULT '0' NOT NULL,
    response_last int(11) DEFAULT '0' NOT NULL,
    response tinyint(3) unsigned DEFAULT '0' NOT NULL,
    time_firstping int(11) DEFAULT '0' NOT NULL,
    time_lastping int(11) DEFAULT '0' NOT NULL,
    time_first_link int(11) DEFAULT '0' NOT NULL,
    time_last_link int(11) DEFAULT '0' NOT NULL,
    firstlink tinyint(4) DEFAULT '0' NOT NULL,
    firstlink_time int(11) DEFAULT '0' NOT NULL,
    secondlink tinyint(4) DEFAULT '0' NOT NULL,
    secondlink_time int(11) DEFAULT '0' NOT NULL,
    thirdlink tinyint(4) DEFAULT '0' NOT NULL,
    thirdlink_time int(11) DEFAULT '0' NOT NULL,
    returned tinyint(4) DEFAULT '0' NOT NULL,
    KEY `mid` (mid)
);

CREATE TABLE fe_users (
    mail_newsletter tinyint(3) unsigned DEFAULT '0' NOT NULL,
    mail_category int(10) unsigned DEFAULT '0' NOT NULL,
    mail_html tinyint(3) unsigned DEFAULT '0' NOT NULL
);

CREATE TABLE tt_address (
    mail_category int(10) unsigned DEFAULT '0' NOT NULL,
    mail_html tinyint(3) unsigned DEFAULT '0' NOT NULL,
);

CREATE TABLE tt_content (
    mail_category int(10) unsigned DEFAULT '0' NOT NULL,
);

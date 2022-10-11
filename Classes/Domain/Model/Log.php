<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use DateTimeImmutable;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class Log extends AbstractEntity
{
    // todo
    // mid int(11) unsigned DEFAULT '0' NOT NULL,
    // rid varchar(11) DEFAULT '0' NOT NULL,
    // email varchar(255) DEFAULT '' NOT NULL,
    // rtbl char(1) DEFAULT '' NOT NULL,
    // tstamp int(11) unsigned DEFAULT '0' NOT NULL,
    // url tinyblob NULL,
    // size int(11) unsigned DEFAULT '0' NOT NULL,
    // parsetime int(11) unsigned DEFAULT '0' NOT NULL,
    // response_type tinyint(4) DEFAULT '0' NOT NULL,
    // html_sent tinyint(4) DEFAULT '0' NOT NULL,
    // url_id tinyint(4) DEFAULT '0' NOT NULL,
    // return_content mediumblob NULL,
    // return_code smallint(6) DEFAULT '0' NOT NULL,
    protected Mail $mail;
    protected SimpleRecipientInterface $recipient;
    protected string $recipientTable = '';
    protected string $recipientUid = '';
    protected string $email = '';
    protected string $url = '';
    protected int $size = 0;
    protected int $parseTime = 0;
    protected int $responseType = 0;
    protected int $formatSent = 0;
    protected int $urlId = 0;
    protected string $returnContent;
    protected int $returnCode = 0;
    protected ?DateTimeImmutable $lastChange;
}

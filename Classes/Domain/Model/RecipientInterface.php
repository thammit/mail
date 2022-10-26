<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

interface RecipientInterface extends SimpleRecipientInterface
{
    /**
     * m=male;f=female;v=various;empty=unknown
     * See MEDIAESSENZ\Mail\Type\Enumeration\Gender
     * @return string
     */
    public function getGender(): string;

    /**
     * @return string
     */
    public function getFirstName(): string;

    /**
     * @return string
     */
    public function getLastName(): string;

    /**
     * True if recipient accepts html mails
     * @return bool
     */
    public function isAcceptsHtml(): bool;

    /**
     * @return ObjectStorage
     */
    public function getCategories(): ObjectStorage;

    /**
     * Record identifier
     * e.g. MEDIAESSENZ\Mail\Domain\Model\Address:123 or MEDIAESSENZ\Mail\Domain\Model\FrontendUser:123
     * @return string
     */
    public function getRecordIdentifier(): string;
}

<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Domain\Model\Category;

interface RecipientInterface
{
    /**
     * @return int|null
     */
    public function getUid(): ?int;

    /**
     * Return true if recipient is activated to receive mails
     * @return bool
     */
    public function isActive(): bool;

    /**
     * Return true if recipient accepts html mails
     * @return bool
     */
    public function isMailHtml(): bool;

    /**
     * @return string
     */
    public function getEmail(): string;

    /**
     * @return string
     */
    public function getName(): string;

    /**
     * @return ObjectStorage<Category>
     */
    public function getCategories(): ObjectStorage;

    /**
     * Full record identifier
     * Can contain an enhanced model to load more data of the recipient which can be handy inside an EventListener
     * See NormalizeRecipientData EventListener for example
     * e.g. MEDIAESSENZ\Mail\Domain\Model\Address:123 or MEDIAESSENZ\Mail\Domain\Model\FrontendUser:123
     * @return string
     */
    public function getRecordIdentifier(): string;

    /**
     * Return all field/values used by the csv export in the recipient module
     * ['uid' => 1, 'email' => 'recipient@gmail.com', 'name' => 'Recipient Name']
     * @return array
     */
    public function getEnhancedData(): array;
}

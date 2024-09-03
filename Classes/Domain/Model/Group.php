<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use MEDIAESSENZ\Mail\Type\Enumeration\CsvEnclosure;
use MEDIAESSENZ\Mail\Type\Enumeration\CsvSeparator;
use MEDIAESSENZ\Mail\Type\Enumeration\CsvType;
use MEDIAESSENZ\Mail\Type\Enumeration\RecipientGroupType;
use MEDIAESSENZ\Mail\Utility\CsvUtility;
use MEDIAESSENZ\Mail\Utility\RecipientUtility;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Group extends AbstractEntity
{
    /**
     * @var int
     */
    protected int $type = RecipientGroupType::PAGES;

    /**
     * @var string
     */
    protected string $title = '';

    /**
     * @var string
     */
    protected string $description = '';

    /**
     * @var string
     */
    protected string $query = '';

    /**
     * @var int
     */
    protected int $staticList;

    /**
     * @var string
     */
    protected string $list = '';

    /**
     * @var int
     */
    protected int $csvSeparator = CsvSeparator::COMMA;

    /**
     * @var int
     */
    protected int $csvEnclosure = CsvEnclosure::DOUBLE_QUOTE;

    /**
     * @var bool
     */
    protected bool  $csvFieldNames = false;

    /**
     * @var string
     */
    protected string $csvData = '';

    /**
     * @var int
     */
    protected int $csvType = CsvType::PLAIN;

    /**
     * @var FileReference|null
     */
    protected ?FileReference $csvFile = null;

    /**
     * @var bool
     */
    protected bool $mailHtml = false;

    /**
     * @var string
     */
    protected string $pages = '';

    /**
     * @var string
     */
    protected string $recipientSources = '';

    /**
     * @var bool
     */
    protected bool $recursive = false;

    /**
     * @var ObjectStorage<Group>
     */
    protected ObjectStorage $children;

    /**
     * @var ObjectStorage<Category>
     */
    protected ObjectStorage $categories;

    public function __construct()
    {
        $this->initializeObject();
    }

    public function initializeObject(): void
    {
        $this->children ??= new ObjectStorage();
        $this->categories ??= new ObjectStorage();
    }

    /**
     * @return int
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * @param int $type
     * @return Group
     */
    public function setType(int $type): Group
    {
        $this->type = $type;
        return $this;
    }

    public function isPages(): bool
    {
        return $this->type === RecipientGroupType::PAGES;
    }

    public function isStatic(): bool
    {
        return $this->type === RecipientGroupType::STATIC;
    }

    public function isCsv(): bool
    {
        return $this->type === RecipientGroupType::CSV;
    }

    public function isPlain(): bool
    {
        return $this->type === RecipientGroupType::PLAIN;
    }

    public function isOther(): bool
    {
        return $this->type === RecipientGroupType::OTHER;
    }

    /**
     * @return string
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @param string $title
     * @return Group
     */
    public function setTitle(string $title): Group
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @param string $description
     * @return Group
     */
    public function setDescription(string $description): Group
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @return string
     */
    public function getQuery(): string
    {
        return $this->query ?? '';
    }

    /**
     * @param string $query
     * @return Group
     */
    public function setQuery(string $query): Group
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @return int
     */
    public function getStaticList(): int
    {
        return $this->staticList;
    }

    /**
     * @param int $staticList
     * @return Group
     */
    public function setStaticList(int $staticList): Group
    {
        $this->staticList = $staticList;
        return $this;
    }

    /**
     * @return string
     */
    public function getList(): string
    {
        return $this->list;
    }

    /**
     * @param string $list
     * @return Group
     */
    public function setList(string $list): Group
    {
        $this->list = $list;
        return $this;
    }

    public function getListRecipients(): array
    {
        return RecipientUtility::normalizePlainEmailList(array_unique(preg_split('|[[:space:],;]+|', trim($this->list))));
    }

    public function getListRecipientsWithName(): array
    {
        return RecipientUtility::normalizePlainEmailList(array_unique(preg_split('|[[:space:],;]+|', trim($this->list))), true);
    }

    public function getCsvSeparator(): int
    {
        return $this->csvSeparator;
    }

    public function getCsvSeparatorString(): string
    {
        return match ($this->csvSeparator) {
            CsvSeparator::TAB => "\t",
            CsvSeparator::SEMICOLON => ';',
            default => ',',
        };
    }

    public function setCsvSeparator(int $csvSeparator): Group
    {
        $this->csvSeparator = $csvSeparator;
        return $this;
    }

    public function getCsvEnclosure(): int
    {
        return $this->csvEnclosure;
    }

    public function getCsvEnclosureString(): string
    {
        return match ($this->csvEnclosure) {
            CsvEnclosure::SINGLE_QUOTE => "'",
            CsvEnclosure::BACK_TICK => '`',
            default => '"',
        };
    }

    public function setCsvEnclosure(int $csvEnclosure): Group
    {
        $this->csvEnclosure = $csvEnclosure;
        return $this;
    }

    public function isCsvFieldNames(): bool
    {
        return $this->csvFieldNames;
    }

    public function setCsvFieldNames(bool $csvFieldNames): Group
    {
        $this->csvFieldNames = $csvFieldNames;
        return $this;
    }

    public function getCsvData(): string
    {
        return $this->csvData;
    }

    public function setCsvData(string $csvData): Group
    {
        $this->csvData = $csvData;
        return $this;
    }

    public function getCsvType(): int
    {
        return $this->csvType;
    }

    public function setCsvType(int $csvType): Group
    {
        $this->csvType = $csvType;
        return $this;
    }

    public function getCsvFile(): ?FileReference
    {
        return $this->csvFile;
    }

    public function setCsvFile(?FileReference $csvFile): Group
    {
        $this->csvFile = $csvFile;
        return $this;
    }

    /**
     * @throws ExtensionConfigurationPathDoesNotExistException
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     */
    public function getCsvRecipients(): array
    {
        return CsvUtility::getRecipientDataFromCSVGroup($this, true);
    }

    /**
     * @return bool
     */
    public function isMailHtml(): bool
    {
        return $this->mailHtml;
    }

    /**
     * @param bool $mailHtml
     * @return Group
     */
    public function setMailHtml(bool $mailHtml): Group
    {
        $this->mailHtml = $mailHtml;
        return $this;
    }

    /**
     * @return string
     */
    public function getPages(): string
    {
        return $this->pages;
    }

    /**
     * @param string $pages
     * @return Group
     */
    public function setPages(string $pages): Group
    {
        $this->pages = $pages;
        return $this;
    }

    /**
     * @return array
     */
    public function getRecipientSources(): array
    {
        return GeneralUtility::trimExplode(',', $this->recipientSources, true);
    }

    /**
     * @return bool
     */
    public function isRecursive(): bool
    {
        return $this->recursive;
    }

    /**
     * @param bool $recursive
     * @return Group
     */
    public function setRecursive(bool $recursive): Group
    {
        $this->recursive = $recursive;
        return $this;
    }

    /**
     * @return ObjectStorage
     */
    public function getChildren(): ObjectStorage
    {
        return $this->children;
    }

    /**
     * @param ObjectStorage<Group> $children
     * @return Group
     */
    public function setChildren(ObjectStorage $children): Group
    {
        $this->children = $children;
        return $this;
    }

    /**
     * @return ObjectStorage
     */
    public function getCategories(): ObjectStorage
    {
        return $this->categories;
    }

    /**
     * @param ObjectStorage<Category> $categories
     * @return Group
     */
    public function setCategories(ObjectStorage $categories): Group
    {
        $this->categories = $categories;
        return $this;
    }

}

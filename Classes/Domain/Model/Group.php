<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use MEDIAESSENZ\Mail\Type\Enumeration\RecordType;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class Group extends AbstractEntity
{
    /**
     * @var int
     */
    protected int $type;

    /**
     * @var string
     */
    protected string $title;

    /**
     * @var string
     */
    protected string $description;

    /**
     * @var string
     */
    protected string $query;

    /**
     * @var int
     */
    protected int $staticList;

    /**
     * @var string
     */
    protected string $list;

    /**
     * @var bool
     */
    protected bool $csv = false;

    /**
     * @var string
     */
    protected string $pages;

    /**
     * @var int
     */
    protected int $recordTypes;

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
        $this->children = new ObjectStorage();
        $this->categories = new ObjectStorage();
    }

    public function initializeObject(): void
    {
        $this->children = $this->children ?? new ObjectStorage();
        $this->categories = $this->categories ?? new ObjectStorage();
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
        return $this->query;
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

    /**
     * @return bool
     */
    public function isCsv(): bool
    {
        return $this->csv;
    }

    /**
     * @param bool $csv
     * @return Group
     */
    public function setCsv(bool $csv): Group
    {
        $this->csv = $csv;
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
     * @return int
     */
    public function getRecordTypes(): int
    {
        return $this->recordTypes;
    }

    public function hasAddress(): bool
    {
        return ($this->recordTypes & RecordType::ADDRESS) !== 0;
    }

    public function hasFrontendUser(): bool
    {
        return ($this->recordTypes & RecordType::FRONTEND_USER) !== 0;
    }

    public function hasCustom(): bool
    {
        return ($this->recordTypes & RecordType::CUSTOM) !== 0;
    }

    public function hasFrontendUserGroup(): bool
    {
        return ($this->recordTypes & RecordType::FRONTEND_USER_GROUP) !== 0;
    }

    /**
     * @param int $recordTypes
     * @return Group
     */
    public function setRecordTypes(int $recordTypes): Group
    {
        $this->recordTypes = $recordTypes;
        return $this;
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

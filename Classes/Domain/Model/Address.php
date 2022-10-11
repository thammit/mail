<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use MEDIAESSENZ\Mail\Enumeration\Gender;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;
use TYPO3\CMS\Extbase\Domain\Model\Category;

class Address extends \FriendsOfTYPO3\TtAddress\Domain\Model\Address implements RecipientInterface
{
    /**
     * @var string
     */
    protected $gender = Gender::UNKNOWN;

    /**
     * @var bool
     */
    protected bool $acceptsHtml = false;

    /**
     * @return bool
     */
    public function isAcceptsHtml(): bool
    {
        return $this->acceptsHtml;
    }

    /**
     * @param bool $acceptsHtml
     */
    public function setAcceptsHtml(bool $acceptsHtml): void
    {
        $this->acceptsHtml = $acceptsHtml;
    }

    /**
     * @param Category $category
     */
    public function addCategory(Category $category): void
    {
        $this->categories->attach($category);
    }

    /**
     * @param Category $category
     */
    public function removeCategory(Category $category): void
    {
        $this->categories->detach($category);
    }

    public function removeAllCategories(): void
    {
        $this->categories = new ObjectStorage();
    }

    public function getRecordIdentifier(): string
    {
        return static::class . ':' . $this->uid;
    }
}

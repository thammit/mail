<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use TYPO3\CMS\Extbase\Domain\Model\Category;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

abstract class AbstractRecipient extends AbstractEntity
{
    /**
     * @var bool
     */
    protected bool $disable = false;

    /**
     * @var bool
     */
    protected bool $active = false;

    /**
     * @var bool
     */
    protected bool $acceptsHtml = false;

    /**
     * @var string
     */
    protected string $email = '';

    /**
     * @var string
     */
    protected string $name = '';

    /**
     * @var ObjectStorage<Category>|null
     */
    protected ?ObjectStorage $categories;

    public function __construct()
    {
        $this->categories = new ObjectStorage();
    }

    /**
     * Called again with initialize object, as fetching an entity from the DB does not use the constructor
     */
    public function initializeObject()
    {
        $this->categories = $this->categories ?? new ObjectStorage();
    }

    /**
     * @return bool
     */
    public function isDisable(): bool
    {
        return $this->disable;
    }

    /**
     * @param bool $disable
     */
    public function setDisable(bool $disable): void
    {
        $this->disable = $disable;
    }

    public function isActive(): bool
    {
        return $this->active && !$this->disable;
    }

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

    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     */
    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function getCategories(): ObjectStorage
    {
        return $this->categories;
    }

    /**
     * @param ObjectStorage<Category> $categories
     */
    public function setCategories(ObjectStorage $categories)
    {
        $this->categories = $categories;
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
}

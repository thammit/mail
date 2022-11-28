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
    protected bool $mailHtml = false;

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
     * @param bool $active
     */
    public function setActive(bool $active): void
    {
        $this->active = $active;
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
     */
    public function setMailHtml(bool $mailHtml): void
    {
        $this->mailHtml = $mailHtml;
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

    public function getCsvExportData(): array
    {
        $categories = [];
        if ($this->categories->count() > 0) {
            foreach ($this->categories as $category) {
                $categories[] = $category->getTitle();
            }
        }
        return [
            'uid' => $this->uid,
            'email' => $this->email,
            'name' => $this->name,
            'mail_active' => $this->active ? '1' : '0',
            'mail_html' => $this->mailHtml,
            'categories' => implode(', ', $categories)
        ];
    }
}

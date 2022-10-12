<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use MEDIAESSENZ\Mail\Enumeration\Gender;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;
use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

class FrontendUser extends AbstractEntity implements RecipientInterface
{

    /**
     * @var string
     */
    protected string $gender = Gender::UNKNOWN;

    /**
     * @var string
     */
    protected string $name = '';

    /**
     * @var string
     */
    protected string $firstName = '';

    /**
     * @var string
     */
    protected string $lastName = '';

    /**
     * @var string
     */
    protected string $title = '';

    /**
     * @var string
     */
    protected string $email = '';

    /**
     * @var string
     */
    protected string $company = '';

    /**
     * @var bool
     */
    protected bool $acceptsHtml = false;

    /**
     * @var ObjectStorage<Category>
     */
    protected ObjectStorage $categories;

    /**
     * Constructs a new Front-End User
     */
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
     * @return string
     */
    public function getGender(): string
    {
        return $this->gender;
    }

    /**
     * @param string $gender
     * @return FrontendUser
     */
    public function setGender(string $gender): FrontendUser
    {
        $this->gender = $gender;
        return $this;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     * @return FrontendUser
     */
    public function setName(string $name): FrontendUser
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @return string
     */
    public function getFirstName(): string
    {
        return $this->firstName;
    }

    /**
     * @param string $firstName
     * @return FrontendUser
     */
    public function setFirstName(string $firstName): FrontendUser
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @return string
     */
    public function getLastName(): string
    {
        return $this->lastName;
    }

    /**
     * @param string $lastName
     * @return FrontendUser
     */
    public function setLastName(string $lastName): FrontendUser
    {
        $this->lastName = $lastName;
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
     * @return FrontendUser
     */
    public function setTitle(string $title): FrontendUser
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return string
     */
    public function getEmail(): string
    {
        return $this->email;
    }

    /**
     * @param string $email
     * @return FrontendUser
     */
    public function setEmail(string $email): FrontendUser
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @return string
     */
    public function getCompany(): string
    {
        return $this->company;
    }

    /**
     * @param string $company
     * @return FrontendUser
     */
    public function setCompany(string $company): FrontendUser
    {
        $this->company = $company;
        return $this;
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
     * @return FrontendUser
     */
    public function setAcceptsHtml(bool $acceptsHtml): FrontendUser
    {
        $this->acceptsHtml = $acceptsHtml;
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
     * @param ObjectStorage $categories
     * @return FrontendUser
     */
    public function setCategories(ObjectStorage $categories): FrontendUser
    {
        $this->categories = $categories;
        return $this;
    }

    /**
     * @param Category $category
     * @return FrontendUser
     */
    public function addCategory(Category $category): FrontendUser
    {
        $this->categories->attach($category);
        return $this;
    }

    /**
     * @param Category $category
     * @return FrontendUser
     */
    public function removeCategory(Category $category): FrontendUser
    {
        $this->categories->detach($category);
        return $this;
    }

    /**
     * @return FrontendUser
     */
    public function removeAllCategories(): FrontendUser
    {
        $this->categories = new ObjectStorage();
        return $this;
    }

    public function getRecordIdentifier(): string
    {
        return static::class . ':' . $this->uid;
    }
}

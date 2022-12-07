<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

use MEDIAESSENZ\Mail\Utility\LanguageUtility;
use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

class RecipientSource extends AbstractEntity
{
    /**
     * @var string
     */
    private string $title = '';

    /**
     * @var int
     */
    private int $type = 0;

    /**
     * @var string
     */
    private string $tableName;

    /**
     * @var string
     */
    private string $modelName;

    /**
     * @var string
     */
    private string $icon;

    /**
     * @return string
     */
    public function getTitle(): string
    {
        if (str_starts_with($this->title, 'LLL:')) {
            return LanguageUtility::getLanguageService()->sL($this->title);
        }
        return $this->title;
    }

    /**
     * @param string $title
     */
    public function setTitle(string $title): void
    {
        $this->title = $title;
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
     */
    public function setType(int $type): void
    {
        $this->type = $type;
    }

    /**
     * @return string
     */
    public function getTableName(): string
    {
        return $this->tableName;
    }

    /**
     * @param string $tableName
     */
    public function setTableName(string $tableName): void
    {
        $this->tableName = $tableName;
    }

    /**
     * @return string
     */
    public function getModelName(): string
    {
        return $this->modelName;
    }

    /**
     * @param string $modelName
     */
    public function setModelName(string $modelName): void
    {
        $this->modelName = $modelName;
    }

    /**
     * @return string
     */
    public function getIcon(): string
    {
        return $this->icon;
    }

    /**
     * @param string $icon
     */
    public function setIcon(string $icon): void
    {
        $this->icon = $icon;
    }

}

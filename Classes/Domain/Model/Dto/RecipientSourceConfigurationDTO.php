<?php

namespace MEDIAESSENZ\Mail\Domain\Model\Dto;

use MEDIAESSENZ\Mail\Type\Enumeration\RecipientSourceType;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RecipientSourceConfigurationDTO
{
    public int $type;
    public int $pid;
    public int $groupUid;
    public string $identifier;
    public string $title;
    public string $icon;
    public ?string $table;
    public ?string $contains = null;
    public ?string $containsTitle = null;
    public ?string $containsIcon = null;
    public ?string $model = null;
    public bool $ignoreMailActive = false;
    public bool $forceHtmlMail = false;
    public array $csvExportFields = [];
    public array $queryRestrictions = [];
    public array $custom = [];

    public function __construct(string $identifier, array $data)
    {
        if ($data['type'] ?? false) {
            $this->type = $data['type'];
        } else {
            // if type not set guess
            $this->type = match (true) {
                str_starts_with($identifier, 'tx_mail_domain_model_group') => RecipientSourceType::CSV,
                !empty($data['model'] ?? '') => RecipientSourceType::MODEL,
                default => RecipientSourceType::TABLE,
            };
        }
        $this->identifier = $identifier;

        if (!($data['groupUid'] ?? false) && str_starts_with($identifier, 'tx_mail_domain_model_mail')) {
            [, $groupUid] = GeneralUtility::trimExplode(':', $identifier, true);
            $this->groupUid = (int)$groupUid;
        } else {
            $this->groupUid = (int)($data['groupUid'] ?? 0);
        }

        $this->pid = (int)($data['pid'] ?? 0);
        $this->title = $data['title'] ?? $identifier;
        $this->icon = $data['icon'] ?? 'empty-empty';
        $this->table = $data['table'] ?? $identifier;
        if ($this->isCsvOrPlain() || $this->isCsvFile()) {
            $this->table = 'tx_mail_domain_model_group';
        }
        $this->contains = $data['contains'] ?? null;
        $this->containsTitle = $data['containsTitle'] ?? null;
        $this->containsIcon = $data['containsIcon'] ?? null;
        $this->model = $data['model'] ?? null;
        $this->ignoreMailActive = (bool)($data['ignoreMailActive'] ?? false);
        $this->forceHtmlMail = (bool)($data['forceHtmlMail'] ?? false);
        $this->csvExportFields = (array)($data['csvExportFields'] ?? []);
        $this->queryRestrictions = (array)($data['queryRestrictions'] ?? []);
        $this->custom = (array)($data['custom'] ?? []);
    }

    public function isTableSource(): bool
    {
        return $this->type === RecipientSourceType::TABLE;
    }

    public function isModelSource(): bool
    {
        return $this->type === RecipientSourceType::MODEL;
    }

    public function isCsvOrPlain(): bool
    {
        return $this->isCsv() || $this->isPlain();
    }

    public function isPlain(): bool
    {
        return $this->type === RecipientSourceType::PLAIN;
    }

    public function isCsv(): bool
    {
        return $this->type === RecipientSourceType::CSV;
    }

    public function isCsvFile(): bool
    {
        return $this->type === RecipientSourceType::CSVFILE;
    }

    public function isService(): bool
    {
        return $this->type === RecipientSourceType::SERVICE;
    }
}

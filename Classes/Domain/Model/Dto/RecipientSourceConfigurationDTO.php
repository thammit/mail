<?php

namespace MEDIAESSENZ\Mail\Domain\Model\Dto;

class RecipientSourceConfigurationDTO
{
    public string $identifier;
    public string $title;
    public string $icon;
    public string $table;
    public ?string $contains = null;
    public ?string $model = null;
    public bool $ignoreMailActive = false;
    public bool $forceHtmlMail = false;
    public array $csvExportFields = [];
    public array $queryRestrictions = [];
    public array $custom = [];

    public function __construct(string $identifier, array $data)
    {
        $this->identifier = $identifier;
        $this->title = $data['title'] ?? $identifier;
        $this->icon = $data['icon'] ?? 'empty-empty';
        $this->table = $data['table'] ?? $identifier;
        if ($data['noTable'] ?? false) {
            $this->table = null;
        }
        $this->contains = $data['contains'] ?? null;
        $this->model = $data['model'] ?? null;
        $this->ignoreMailActive = $data['ignoreMailActive'] ?? false;
        $this->forceHtmlMail = $data['forceHtmlMail'] ?? false;
        $this->csvExportFields = $data['csvExportFields'] ?? [];
        $this->queryRestrictions = $data['queryRestrictions'] ?? [];
        $this->custom = $data['custom'] ?? [];
    }
}

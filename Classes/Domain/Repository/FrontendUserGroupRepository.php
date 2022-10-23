<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

class FrontendUserGroupRepository
{
    use RepositoryTrait;
    protected string $table = 'fe_groups';
}

<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Repository;

class FrontendUserGroupRepository implements RecipientGroupRepositoryInterface
{
    protected string $table = 'fe_groups';
    use RepositoryTrait;
}

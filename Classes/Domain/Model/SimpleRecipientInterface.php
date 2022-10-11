<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

interface SimpleRecipientInterface
{
    public function getEmail(): string;
    public function getName(): string;
}

<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Domain\Model;

class Address extends AbstractRecipient implements RecipientInterface
{
    public function getRecordIdentifier(): string
    {
        return \FriendsOfTYPO3\TtAddress\Domain\Model\Address::class . ':' . $this->uid;
    }
}

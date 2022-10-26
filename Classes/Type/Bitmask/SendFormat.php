<?php
declare(strict_types=1);

namespace MEDIAESSENZ\Mail\Type\Bitmask;

use TYPO3\CMS\Core\Type\BitSet;
use TYPO3\CMS\Core\Type\TypeInterface;

class SendFormat extends BitSet implements TypeInterface
{
    public const NONE  = 0b0;
    public const PLAIN = 0b1;
    public const HTML  = 0b10;
    public const BOTH  = 0b11;

    /**
     * @param string|int $set
     */
    public function __construct(string|int $set)
    {
        parent::__construct((int)$set);
    }

    /**
     * @param int $format
     * @return bool
     */
    public function hasFormat(int $format): bool
    {
        return $this->get($format);
    }

    /**
     * @param int $format
     * @return void
     */
    public function setFormat(int $format): void
    {
        $this->set = $format;
    }

    /**
     * @return bool
     */
    public function isNone(): bool
    {
        return $this->set === static::NONE;
    }

    /**
     * @return bool
     */
    public function isBoth(): bool
    {
        return $this->get(static::BOTH);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return (string)$this->__toInt();
    }
}

<?php
declare(strict_types=1);
namespace Ivory\Relation;

class CursorProperties
{
    private $holdable;
    private $scrollable;
    private $binary;

    public function __construct(bool $holdable, bool $scrollable, bool $binary)
    {
        $this->holdable = $holdable;
        $this->scrollable = $scrollable;
        $this->binary = $binary;
    }

    /**
     * Tells whether the cursor is _holdable_, and thus will not be closed automatically with the transaction end.
     */
    public function isHoldable(): bool
    {
        return $this->holdable;
    }

    /**
     * Tells whether the cursor is _scrollable_, and thus any moves and fetches of multiple tuples is possible.
     */
    public function isScrollable(): bool
    {
        return $this->scrollable;
    }

    /**
     * Tells whether the cursor is binary, and thus returns binary data rather than plaintext.
     */
    public function isBinary(): bool
    {
        return $this->binary;
    }
}

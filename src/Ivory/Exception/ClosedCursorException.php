<?php
declare(strict_types=1);
namespace Ivory\Exception;

/**
 * Exception thrown when working on a closed cursor.
 */
class ClosedCursorException extends \RuntimeException
{
    public function __construct(string $cursorName)
    {
        parent::__construct("Cursor `$cursorName` is closed");
    }
}

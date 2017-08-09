<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Value\StrictComposite;

/**
 * @property-read string $file
 * @property-read int $line
 * @property-read string|null $message
 */
class ParseError extends StrictComposite
{
    public function __construct(string $file, int $line, ?string $message = null)
    {
        parent::__construct([
            'file' => $file,
            'line' => $line,
            'message' => $message,
        ]);
    }
}

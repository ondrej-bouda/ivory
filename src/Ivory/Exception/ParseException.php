<?php
declare(strict_types=1);
namespace Ivory\Exception;

class ParseException extends \RuntimeException
{
    private $offset;

    public function __construct(string $message, ?int $offset = null, int $code = 0, ?\Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->offset = $offset;
    }

    /**
     * @return int|null offset where the parse error was identified, or <tt>null</tt> if unknown
     */
    final public function getOffset(): ?int
    {
        return $this->offset;
    }
}

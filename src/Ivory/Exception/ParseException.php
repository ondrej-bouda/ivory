<?php
namespace Ivory\Exception;

class ParseException extends \RuntimeException
{
    private $offset;

    public function __construct($message, $offset = null, $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->offset = $offset;
    }

    final public function getOffset()
    {
        return $this->offset;
    }
}

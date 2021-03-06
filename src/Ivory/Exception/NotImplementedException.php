<?php
declare(strict_types=1);
namespace Ivory\Exception;

/**
 * Exception thrown when the code uses a part of Ivory which has not been implemented (yet).
 */
class NotImplementedException extends \LogicException
{
}

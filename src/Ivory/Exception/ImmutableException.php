<?php
declare(strict_types=1);

namespace Ivory\Exception;

/**
 * Exception thrown if an immutable object is to be modified.
 */
class ImmutableException extends \LogicException
{
}

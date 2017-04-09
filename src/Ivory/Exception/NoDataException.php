<?php
namespace Ivory\Exception;

/**
 * Exception thrown when some data should have been set up, but was not. It is a kind of invalid state of the program.
 */
class NoDataException extends InvalidStateException
{
}

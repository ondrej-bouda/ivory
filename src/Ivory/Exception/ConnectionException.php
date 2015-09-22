<?php
namespace Ivory\Exception;

use Exception;
use RuntimeException;

/**
 * An exception above a database connection.
 *
 * This class of exceptions gets thrown upon an error with the database connection itself. For errors on querying the
 * database, a {@link CommandException} is used.
 */
class ConnectionException extends \RuntimeException
{
	/**
	 * @param string|resource $message alternatively to the message, a connection handler may be given - the last error
	 *                                   message on this connection is considered then
	 * @param int $code
	 * @param Exception $previous
	 */
	public function __construct($message = '', $code = 0, Exception $previous = null)
	{
		if (is_resource($message)) {
			$message = pg_last_error($message);
		}

		parent::__construct($message, $code, $previous);
	}

}
<?php
namespace Ivory\Exception;

use RuntimeException;

/**
 * Exception thrown upon errors on querying the database.
 *
 * The exception code, if provided contains the SQLSTATE code.
 */
class ResultException extends \RuntimeException
{
	private $messageDetail = null;
	private $messageHint = null;


	/**
	 * @param string|resource $message alternatively to the message, a PostgreSQL query result resource may be given -
	 *                                   the primary error message associated with the result resource is in such case
	 *                                   used as the exception message, and message detail and message hint get
	 *                                   initialized, too (see {@link getMessageDetail()} and {@link getMessageHint()})
	 * @param int $code the SQLSTATE code of the error; irrelevant if $message is a PostgreSQL query result resource
	 * @param \Exception $previous
	 */
	public function __construct($message = '', $code = 0, \Exception $previous = null)
	{
		if (is_resource($message)) {
			$res = $message;
			$message = pg_result_error_field($res, PGSQL_DIAG_MESSAGE_PRIMARY);
			$code = pg_result_error_field($res, PGSQL_DIAG_SQLSTATE);
			$this->messageDetail = pg_result_error_field($res, PGSQL_DIAG_MESSAGE_DETAIL);
			$this->messageHint = pg_result_error_field($res, PGSQL_DIAG_MESSAGE_HINT);
		}

		parent::__construct($message, $code, $previous);
	}

	/**
	 * @return string|null the detail message, if any
	 */
	final public function getMessageDetail()
	{
		return $this->messageDetail;
	}

	/**
	 * @return string|null the message hint, if any
	 */
	final public function getMessageHint()
	{
		return $this->messageHint;
	}
}

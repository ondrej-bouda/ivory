<?php
namespace Ivory\Exception;

use RuntimeException;

/**
 * Exception thrown upon errors on querying the database.
 *
 * The exception message and code contain the primary error message and the SQLSTATE code, respectively. To recognize
 * SQLSTATE codes, {@link \Ivory\Result\SqlState} constants might be helpful.
 *
 * Besides the primary message and SQLSTATE code, the exception holds the error message detail, error hint, and all the
 * other error diagnostic fields except those referring to PostgreSQL source code (those referred to by the PHP
 * <tt>PGSQL_DIAG_SOURCE_*</tt> constants).
 */
class StatementException extends \RuntimeException
{
	private $query;
	private $errorFields = [];


	/**
	 * @param resource $resultHandler a PostgreSQL query result resource
	 * @param string $query the statement, as sent by the client, which caused the error
	 */
	public function __construct($resultHandler, $query)
	{
		$message = self::inferMessage($resultHandler);
		$code = pg_result_error_field($resultHandler, PGSQL_DIAG_SQLSTATE);

		parent::__construct($message, $code);

		$this->query = $query;

		$fields = [
			PGSQL_DIAG_SEVERITY,
			PGSQL_DIAG_MESSAGE_DETAIL,
			PGSQL_DIAG_MESSAGE_HINT,
			PGSQL_DIAG_STATEMENT_POSITION,
			PGSQL_DIAG_INTERNAL_QUERY,
			PGSQL_DIAG_INTERNAL_POSITION,
			PGSQL_DIAG_CONTEXT,
		];
		foreach ($fields as $f) {
			$this->errorFields[$f] = pg_result_error_field($resultHandler, $f);
		}
	}

	private static function inferMessage($resultHandler)
	{
		$message = pg_result_error_field($resultHandler, PGSQL_DIAG_MESSAGE_PRIMARY);
		if (strlen($message) > 0) {
			return $message;
		}

		switch (pg_result_status($resultHandler)) {
			case PGSQL_EMPTY_QUERY:
				return 'Empty query';
			case PGSQL_BAD_RESPONSE:
				return "The server's response was not understood";
			case PGSQL_NONFATAL_ERROR:
				return 'Non-fatal error';
			case PGSQL_FATAL_ERROR:
				return 'Fatal error';
			default:
				return 'Unknown error';
		}
	}

	/**
	 * @return string the severity of the error;
	 *                one of <tt>ERROR</tt>, <tt>FATAL</tt>, or <tt>PANIC</tt> (for errors), or <tt>WARNING</tt>,
	 *                  <tt>NOTICE</tt>, <tt>DEBUG</tt>, <tt>INFO</tt>, or <tt>LOG</tt> (in a notice message), or a
	 *                  localized translation of one of these
	 */
	final public function getSeverity()
	{
		return $this->errorFields[PGSQL_DIAG_SEVERITY];
	}

	/**
	 * @return string|null the detailed message, if any
	 */
	final public function getMessageDetail()
	{
		return $this->errorFields[PGSQL_DIAG_MESSAGE_DETAIL];
	}

	/**
	 * @return string|null the message hint, if any
	 */
	final public function getMessageHint()
	{
		return $this->errorFields[PGSQL_DIAG_MESSAGE_HINT];
	}

	/**
	 * @return string the original statement, as sent by the client, which caused the error
	 */
	final public function getQuery()
	{
		return $this->query;
	}

	/**
	 * @return int|null position of the error within the original statement, returned by {@link getQuery()};
	 *                  one-based, measured in characters (not bytes)
	 */
	final public function getStatementPosition()
	{
		return $this->errorFields[PGSQL_DIAG_STATEMENT_POSITION];
	}

	/**
	 * @return string|null the text of the internally-generated query which actually caused the error (if any);
	 *                     e.g., a query issued from within a PostgreSQL function
	 */
	final public function getInternalQuery()
	{
		return $this->errorFields[PGSQL_DIAG_INTERNAL_QUERY];
	}

	/**
	 * @return int|null position of the error within the {@link getInternalQuery() internally-generated query} which
	 *                    actually caused the error (if any);
	 *                  one-based, measured in characters (not bytes)
	 *
	 */
	final public function getInternalPosition()
	{
		return $this->errorFields[PGSQL_DIAG_INTERNAL_POSITION];
	}

	/**
	 * @return string|null the PostgreSQL context of the error; e.g., the stack trace of functions and internal queries
	 */
	final public function getContext()
	{
		return $this->errorFields[PGSQL_DIAG_CONTEXT];
	}
}

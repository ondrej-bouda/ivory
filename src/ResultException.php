<?php
namespace Ivory;

/**
 * Exception thrown upon errors on querying the database.
 */
class ResultException extends \RuntimeException
{
	// TODO: accept query result resource alternatively to the error message and code - take the error message above the query result, and SQLSTATE as the exception code
	// see http://php.net/manual/en/function.pg-result-error-field.php
}

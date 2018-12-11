<?php
declare(strict_types=1);
namespace Ivory\Exception;

use Ivory\Lang\Sql\SqlState;

/**
 * Exception thrown upon errors on querying the database.
 *
 * The exception message contains the primary error message. To get the SQL State code, use {@link getSqlStateCode()}
 * method. To recognize SQLSTATE codes, {@link \Ivory\Result\SqlState} constants might be helpful. Alternatively,
 * {@link StatementException::getSqlState()} may be used to get an {@link SqlState} object.
 *
 * Besides the primary message and SQLSTATE code, the exception holds the error message detail, error hint, and all the
 * other error diagnostic fields except those referring to PostgreSQL source code (those referred to by the PHP
 * `PGSQL_DIAG_SOURCE_*` constants).
 *
 * This class is expected to be sub-classed by custom exception classes. *Any such subclasses are required to have the
 * same constructor arguments as this class, and are required to pass these arguments to the constructor of this class.*
 * See {@link StatementExceptionFactory} and its usages - either the global
 * {@link \Ivory\Ivory::getStatementExceptionFactory()} or the local
 * {@link \Ivory\Connection::getStatementExceptionFactory()} - for details on using custom statement exceptions.
 */
class StatementException extends \RuntimeException
{
    private $query;
    private $errorFields = [];


    /**
     * @param resource $resultHandler a PostgreSQL query result resource
     * @param string $query the statement, as sent by the client, which caused the error
     */
    public function __construct($resultHandler, string $query)
    {
        $message = self::inferMessage($resultHandler);

        parent::__construct($message); // NOTE: SQL state code (string) cannot be used as the exception code (int)

        $this->query = $query;

        $fields = [
            PGSQL_DIAG_SQLSTATE,
            PGSQL_DIAG_SEVERITY,
            PGSQL_DIAG_MESSAGE_DETAIL,
            PGSQL_DIAG_MESSAGE_HINT,
            PGSQL_DIAG_STATEMENT_POSITION,
            PGSQL_DIAG_INTERNAL_QUERY,
            PGSQL_DIAG_INTERNAL_POSITION,
            PGSQL_DIAG_CONTEXT,
        ];
        if (PHP_VERSION_ID >= 70300) {
            /** @noinspection PhpUndefinedConstantInspection */
            $fields[] = PGSQL_DIAG_SEVERITY_NONLOCALIZED;
        }
        foreach ($fields as $f) {
            $this->errorFields[$f] = pg_result_error_field($resultHandler, $f);
        }
    }

    private static function inferMessage($resultHandler): string
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

    final public function getSqlStateCode(): string
    {
        return $this->errorFields[PGSQL_DIAG_SQLSTATE];
    }

    final public function getSqlState(): SqlState
    {
        return SqlState::fromCode($this->getSqlStateCode());
    }

    /**
     * Returns the localized severity of the error.
     *
     * @return string one of <tt>ERROR</tt>, <tt>FATAL</tt>, or <tt>PANIC</tt> (for errors), or <tt>WARNING</tt>,
     *                  <tt>NOTICE</tt>, <tt>DEBUG</tt>, <tt>INFO</tt>, or <tt>LOG</tt> (in a notice message), or a
     *                  localized translation of one of these
     */
    final public function getSeverity(): string
    {
        return $this->errorFields[PGSQL_DIAG_SEVERITY];
    }

    /**
     * Returns the nonlocalized severity of the error.
     *
     * @since PostgreSQL 9.6
     * @since PHP 7.3
     * @return string one of <tt>ERROR</tt>, <tt>FATAL</tt>, or <tt>PANIC</tt> (for errors), or <tt>WARNING</tt>,
     *                  <tt>NOTICE</tt>, <tt>DEBUG</tt>, <tt>INFO</tt>, or <tt>LOG</tt> (in a notice message);
     * @throws UnsupportedException if used in PHP < 7.3 or PostgreSQL < 9.6 as such older versions do not support the
     *                              nonlocalized severity
     */
    final public function getNonlocalizedSeverity(): string
    {
        if (PHP_VERSION_ID < 70300) {
            throw new UnsupportedException('Nonlocalized severity is only supported on PHP >= 7.3');
        }
        /** @noinspection PhpUndefinedConstantInspection */
        if (!isset($this->errorFields[PGSQL_DIAG_SEVERITY_NONLOCALIZED])) {
            throw new UnsupportedException('Nonlocalized severity is only supported on PostgreSQL >= 9.6');
        }
        /** @noinspection PhpUndefinedConstantInspection */
        return $this->errorFields[PGSQL_DIAG_SEVERITY_NONLOCALIZED];
    }

    /**
     * @return string|null the detailed message, if any
     */
    final public function getMessageDetail(): ?string
    {
        return $this->errorFields[PGSQL_DIAG_MESSAGE_DETAIL];
    }

    /**
     * @return string|null the message hint, if any
     */
    final public function getMessageHint(): ?string
    {
        return $this->errorFields[PGSQL_DIAG_MESSAGE_HINT];
    }

    /**
     * @return string the original statement, as sent by the client, which caused the error
     */
    final public function getQuery(): string
    {
        return $this->query;
    }

    /**
     * @return int|null position of the error within the original statement, returned by {@link getQuery()};
     *                  one-based, measured in characters (not bytes)
     */
    final public function getStatementPosition(): ?int
    {
        if (isset($this->errorFields[PGSQL_DIAG_STATEMENT_POSITION])) {
            return (int)$this->errorFields[PGSQL_DIAG_STATEMENT_POSITION];
        } else {
            return null;
        }
    }

    /**
     * @return string|null the text of the internally-generated query which actually caused the error (if any);
     *                     e.g., a query issued from within a PostgreSQL function
     */
    final public function getInternalQuery(): ?string
    {
        return $this->errorFields[PGSQL_DIAG_INTERNAL_QUERY];
    }

    /**
     * @return int|null position of the error within the {@link getInternalQuery() internally-generated query} which
     *                    actually caused the error (if any);
     *                  one-based, measured in characters (not bytes)
     */
    final public function getInternalPosition(): ?int
    {
        if (isset($this->errorFields[PGSQL_DIAG_INTERNAL_POSITION])) {
            return (int)$this->errorFields[PGSQL_DIAG_INTERNAL_POSITION];
        } else {
            return null;
        }
    }

    /**
     * @return string|null the PostgreSQL context of the error; e.g., the stack trace of functions and internal queries
     */
    final public function getContext(): ?string
    {
        return $this->errorFields[PGSQL_DIAG_CONTEXT];
    }
}

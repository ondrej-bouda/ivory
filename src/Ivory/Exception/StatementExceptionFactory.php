<?php
namespace Ivory\Exception;

use Ivory\Result\SqlState;

/**
 * Factory emitting {@link StatementException}s upon errors on querying the database.
 *
 * Custom rules may yield emitting specific subclasses of {@link StatementException}, either based on the SQLSTATE code
 * or the primary message.
 *
 * The order of trying a statement error to match the exceptions is as follows:
 * 1. exact match on the SQLSTATE code is tried, preferably those exception classes matching the error message,
 * 2. exact match on the SQLSTATE class is tried,
 * 3. PREG match is iteratively tried for each registered regular expression, in the registration order.
 */
class StatementExceptionFactory
{
    /** @var string[][] map: SQLSTATE code => map: regex => exception class name */
    private $bySqlStateCodeAndMessage = [];
    /** @var string[] map: SQLSTATE code => exception class name */
    private $bySqlStateCode = [];
    /** @var string[] map: SQLSTATE class => exception class name */
    private $bySqlStateClass = [];
    /** @var string[] map: regex => exception class name */
    private $byMessage = [];


    /**
     * @param string $sqlStateCode see {@link \Ivory\Result\SqlState} constants
     * @param string $preg Perl-compatible regular expression, given to {@link preg_match()}, to match the error
     * @param string $exceptionClass name of class, inheriting from {@link StatementException}, to be thrown upon a
     *                                 statement error with the SQLSTATE code and message matching the regular
     *                                 expression
     * @return $this
     */
    public function registerBySqlStateCodeAndMessage($sqlStateCode, $preg, $exceptionClass)
    {
        assert(is_a($exceptionClass, StatementException::class, true));
        if (!isset($this->bySqlStateCodeAndMessage[$sqlStateCode])) {
            $this->bySqlStateCodeAndMessage[$sqlStateCode] = [];
        }
        $this->bySqlStateCodeAndMessage[$sqlStateCode][$preg] = $exceptionClass;
        return $this;
    }

    /**
     * @param string $sqlStateCode see {@link \Ivory\Result\SqlState} constants
     * @param string $exceptionClass name of class, inheriting from {@link StatementException}, to be thrown upon a
     *                                 statement error with the SQLSTATE code
     * @return $this
     */
    public function registerBySqlStateCode($sqlStateCode, $exceptionClass)
    {
        assert(is_a($exceptionClass, StatementException::class, true));
        $this->bySqlStateCode[$sqlStateCode] = $exceptionClass;
        return $this;
    }

    /**
     * @param string $sqlStateClass see {@link \Ivory\Result\SqlStateClass} constants
     * @param string $exceptionClass name of class, inheriting from {@link StatementException}, to be thrown upon a
     *                                 statement error with an SQLSTATE code falling under the given SQLSTATE class
     * @return $this
     */
    public function registerBySqlStateClass($sqlStateClass, $exceptionClass)
    {
        assert(is_a($exceptionClass, StatementException::class, true));
        $this->bySqlStateClass[$sqlStateClass] = $exceptionClass;
        return $this;
    }

    /**
     * @param string $preg Perl-compatible regular expression, given to {@link preg_match()}, to match the error
     * @param string $exceptionClass
     * @return $this
     */
    public function registerByMessage($preg, $exceptionClass)
    {
        assert(is_a($exceptionClass, StatementException::class, true));
        $this->byMessage[$preg] = $exceptionClass;
        return $this;
    }


    /**
     * @param resource $resultHandler a PostgreSQL query result resource
     * @param string $query the statement, as sent by the client, which caused the error
     * @param StatementExceptionFactory $fallbackFactory
     * @return StatementException
     */
    public function createException($resultHandler, $query, StatementExceptionFactory $fallbackFactory = null)
    {
        $exClass = $this->inferExceptionClass($resultHandler, $fallbackFactory);
        assert(is_a($exClass, StatementException::class, true));
        return new $exClass($resultHandler, $query);
    }

    private function inferExceptionClass($resultHandler, StatementExceptionFactory $fallbackFactory = null)
    {
        if ($this->bySqlStateCodeAndMessage || $this->bySqlStateCode || $this->bySqlStateClass) {
            $sqlStateCode = pg_result_error_field($resultHandler, PGSQL_DIAG_SQLSTATE);

            if (isset($this->bySqlStateCodeAndMessage[$sqlStateCode])) {
                $msg = pg_result_error_field($resultHandler, PGSQL_DIAG_MESSAGE_PRIMARY);
                foreach ($this->bySqlStateCodeAndMessage[$sqlStateCode] as $preg => $exClass) {
                    if (preg_match($preg, $msg)) {
                        return $exClass;
                    }
                }
            }

            if (isset($this->bySqlStateCode[$sqlStateCode])) {
                return $this->bySqlStateCode[$sqlStateCode];
            }

            if ($this->bySqlStateClass) {
                $sqlStateClass = SqlState::fromCode($sqlStateCode)->getClass();
                if (isset($this->bySqlStateClass[$sqlStateClass])) {
                    return $this->bySqlStateClass[$sqlStateClass];
                }
            }
        }

        if ($this->byMessage) {
            $msg = pg_result_error_field($resultHandler, PGSQL_DIAG_MESSAGE_PRIMARY);
            foreach ($this->byMessage as $preg => $exClass) {
                if (preg_match($preg, $msg)) {
                    return $exClass;
                }
            }
        }

        if ($fallbackFactory !== null) {
            return $fallbackFactory->inferExceptionClass($resultHandler);
        }
        else {
            return StatementException::class;
        }
    }
}
<?php
namespace Ivory;

use Ivory\Connection\ConnectionParameters;
use Ivory\Connection\IConnection;
use Ivory\Exception\StatementExceptionFactory;
use Ivory\Lang\SqlPattern\SqlPatternParser;
use Ivory\Type\TypeRegister;

/**
 * Specification for factory of objects used in the core of Ivory.
 */
interface ICoreFactory
{
    function createTypeRegister(): TypeRegister;

    function createConnection(string $connName, ConnectionParameters $params): IConnection;

    function createSqlPatternParser(): SqlPatternParser;

    function createStatementExceptionFactory(): StatementExceptionFactory;
}

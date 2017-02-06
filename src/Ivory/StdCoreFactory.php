<?php
namespace Ivory;

use Ivory\Connection\Connection;
use Ivory\Connection\ConnectionParameters;
use Ivory\Connection\IConnection;
use Ivory\Exception\StatementExceptionFactory;
use Ivory\Lang;
use Ivory\Lang\SqlPattern\SqlPatternParser;
use Ivory\Type\Ivory\CommandRecipeType;
use Ivory\Type\Ivory\IdentifierType;
use Ivory\Type\Ivory\QuotedIdentifierType;
use Ivory\Type\Ivory\RelationRecipeType;
use Ivory\Type\Ivory\SqlType;
use Ivory\Type\Std\StdRangeCanonicalFuncProvider;
use Ivory\Type\Std\StdTypeLoader;
use Ivory\Type\TypeRegister;

/**
 * The standard factory for the core Ivory objects.
 *
 * The factory may either be inherited or replaced altogether to change any default behaviour of Ivory. The user
 * supplied factory may be activated with {@link Ivory::setCoreFactory()}. Note, however, that {@link Ivory} caches the
 * objects so setting another core factory is only effective at the very beginning of working with Ivory.
 */
class StdCoreFactory implements ICoreFactory
{
    public function createTypeRegister() : TypeRegister
    {
        $reg = new TypeRegister();
        $reg->registerTypeLoader(new StdTypeLoader());
        $reg->registerRangeCanonicalFuncProvider(new StdRangeCanonicalFuncProvider());

        $reservedTypes = Lang\Sql\Types::getReservedTypes();
        foreach ($reservedTypes as $alias => list($implSchema, $implName)) {
            $reg->registerTypeAbbreviation($alias, $implSchema, $implName);
        }

        // standard non-volatile type converters for SQL patterns; volatile ones will be defined on the connection
        $reg->registerSqlPatternType('sql', new SqlType());
        $reg->registerSqlPatternType('ident', new IdentifierType());
        $reg->registerSqlPatternType('qident', new QuotedIdentifierType());

        // standard type abbreviations
        $reg->registerTypeAbbreviation('s', 'pg_catalog', 'text');
        $reg->registerTypeAbbreviation('i', 'pg_catalog', 'int');
        $reg->registerTypeAbbreviation('I', 'pg_catalog', 'bigint');
        $reg->registerTypeAbbreviation('num', 'pg_catalog', 'numeric');
        $reg->registerTypeAbbreviation('f', 'pg_catalog', 'float8');
        $reg->registerTypeAbbreviation('ts', 'pg_catalog', 'timestamp');
        $reg->registerTypeAbbreviation('tstz', 'pg_catalog', 'timestamptz');

        return $reg;
    }

    public function createSqlPatternParser() : SqlPatternParser
    {
        return new SqlPatternParser();
    }

    public function createStatementExceptionFactory() : StatementExceptionFactory
    {
        return new StatementExceptionFactory();
    }

    public function createConnection(string $connName, ConnectionParameters $params) : IConnection
    {
        $conn = new Connection($connName, $params);

        // register volatile type converters for SQL patterns
        $reg = $conn->getTypeRegister();
        $reg->registerSqlPatternType('rel', new RelationRecipeType($conn));
        $reg->registerSqlPatternType('cmd', new CommandRecipeType($conn));

        return $conn;
    }
}

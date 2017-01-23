<?php
namespace Ivory;

use Ivory\Connection\Connection;
use Ivory\Connection\ConnectionParameters;
use Ivory\Connection\IConnection;
use Ivory\Exception\StatementExceptionFactory;
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

        // standard non-volatile type converters for SQL patterns; volatile ones will be defined on the connection
        $reg->registerSqlPatternType('sql', new SqlType());
        $reg->registerSqlPatternType('ident', new IdentifierType());
        $reg->registerSqlPatternType('qident', new QuotedIdentifierType());

        // standard type abbreviations
        $reg->registerSqlPatternTypeAbbreviation('s', 'pg_catalog', 'text');
        $reg->registerSqlPatternTypeAbbreviation('d', 'pg_catalog', 'int');
        $reg->registerSqlPatternTypeAbbreviation('D', 'pg_catalog', 'bigint');
        $reg->registerSqlPatternTypeAbbreviation('n', 'pg_catalog', 'numeric');
        $reg->registerSqlPatternTypeAbbreviation('f', 'pg_catalog', 'float8');
        $reg->registerSqlPatternTypeAbbreviation('b', 'pg_catalog', 'bool');
        $reg->registerSqlPatternTypeAbbreviation('a', 'pg_catalog', 'date'); // FIXME: come up with a reasonable abbreviation for the date type
        $reg->registerSqlPatternTypeAbbreviation('t', 'pg_catalog', 'time');
        $reg->registerSqlPatternTypeAbbreviation('ts', 'pg_catalog', 'timestamp');
        $reg->registerSqlPatternTypeAbbreviation('tstz', 'pg_catalog', 'timestamptz');

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

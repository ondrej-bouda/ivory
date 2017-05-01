<?php
namespace Ivory;

use Ivory\Cache\CacheControl;
use Ivory\Cache\ICacheControl;
use Ivory\Connection\Connection;
use Ivory\Connection\ConnectionParameters;
use Ivory\Connection\IConnection;
use Ivory\Exception\StatementExceptionFactory;
use Ivory\Lang;
use Ivory\Lang\SqlPattern\CachingSqlPatternParser;
use Ivory\Lang\SqlPattern\ISqlPatternParser;
use Ivory\Lang\SqlPattern\SqlPatternParser;
use Ivory\Type\Ivory\CommandType;
use Ivory\Type\Ivory\IdentifierType;
use Ivory\Type\Ivory\QuotedIdentifierType;
use Ivory\Type\Ivory\RelationType;
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
    public function createGlobalTypeRegister(): TypeRegister
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
        $reg->registerTypeAbbreviation('i', 'pg_catalog', 'bigint');
        $reg->registerTypeAbbreviation('num', 'pg_catalog', 'numeric');
        $reg->registerTypeAbbreviation('f', 'pg_catalog', 'float8');
        $reg->registerTypeAbbreviation('ts', 'pg_catalog', 'timestamp');
        $reg->registerTypeAbbreviation('tstz', 'pg_catalog', 'timestamptz');

        // standard type recognition rules
        $reg->addTypeRecognitionRule('int', 'pg_catalog', 'int8');
        $reg->addTypeRecognitionRule('string', 'pg_catalog', 'text');
        $reg->addTypeRecognitionRule('bool', 'pg_catalog', 'bool');
        $reg->addTypeRecognitionRule('float', 'pg_catalog', 'float8');
        $reg->addTypeRecognitionRule('null', 'pg_catalog', 'text');
        $reg->addTypeRecognitionRule('array', 'pg_catalog', 'text[]');
        $reg->addTypeRecognitionRule(\Ivory\Value\Decimal::class, 'pg_catalog', 'numeric');
        $reg->addTypeRecognitionRule(\Ivory\Value\Date::class, 'pg_catalog', 'date');
        $reg->addTypeRecognitionRule(\Ivory\Value\Time::class, 'pg_catalog', 'time');
        $reg->addTypeRecognitionRule(\Ivory\Value\TimeTz::class, 'pg_catalog', 'timetz');
        $reg->addTypeRecognitionRule(\Ivory\Value\Timestamp::class, 'pg_catalog', 'timestamp');
        $reg->addTypeRecognitionRule(\Ivory\Value\TimestampTz::class, 'pg_catalog', 'timestamptz');
        $reg->addTypeRecognitionRule(\Ivory\Value\TimeInterval::class, 'pg_catalog', 'interval');
        $reg->addTypeRecognitionRule(\Ivory\Value\FixedBitString::class, 'pg_catalog', 'bit');
        $reg->addTypeRecognitionRule(\Ivory\Value\VarBitString::class, 'pg_catalog', 'varbit');
        $reg->addTypeRecognitionRule(\Ivory\Value\Json::class, 'pg_catalog', 'json');
        $reg->addTypeRecognitionRule(\Ivory\Value\XmlContent::class, 'pg_catalog', 'xml');
        $reg->addTypeRecognitionRule(\Ivory\Value\XmlDocument::class, 'pg_catalog', 'xml');
        $reg->addTypeRecognitionRule(\DOMDocument::class, 'pg_catalog', 'xml');
        $reg->addTypeRecognitionRule(\DOMNode::class, 'pg_catalog', 'xml');
        $reg->addTypeRecognitionRule(\DOMNodeList::class, 'pg_catalog', 'xml');
        $reg->addTypeRecognitionRule(\SimpleXMLElement::class, 'pg_catalog', 'xml');
        $reg->addTypeRecognitionRule(\Ivory\Value\Point::class, 'pg_catalog', 'point');
        $reg->addTypeRecognitionRule(\Ivory\Value\Line::class, 'pg_catalog', 'line');
        $reg->addTypeRecognitionRule(\Ivory\Value\LineSegment::class, 'pg_catalog', 'lseg');
        $reg->addTypeRecognitionRule(\Ivory\Value\Box::class, 'pg_catalog', 'box');
        $reg->addTypeRecognitionRule(\Ivory\Value\Path::class, 'pg_catalog', 'path');
        $reg->addTypeRecognitionRule(\Ivory\Value\Polygon::class, 'pg_catalog', 'polygon');
        $reg->addTypeRecognitionRule(\Ivory\Value\Circle::class, 'pg_catalog', 'circle');
        $reg->addTypeRecognitionRule(\Ivory\Value\NetAddress::class, 'pg_catalog', 'inet');
        $reg->addTypeRecognitionRule(\Ivory\Value\MacAddr::class, 'pg_catalog', 'macaddr');
        $reg->addTypeRecognitionRule(\Ivory\Value\Money::class, 'pg_catalog', 'money');
        $reg->addTypeRecognitionRule(\Ivory\Value\PgLogSequenceNumber::class, 'pg_catalog', 'pg_lsn');
        $reg->addTypeRecognitionRule(\Ivory\Value\TxIdSnapshot::class, 'pg_catalog', 'txid_snapshot');
        $reg->addTypeRecognitionRule(\Ivory\Value\TextSearchVector::class, 'pg_catalog', 'tsvector');
        $reg->addTypeRecognitionRule(\Ivory\Value\TextSearchQuery::class, 'pg_catalog', 'tsquery');

        return $reg;
    }

    public function createSqlPatternParser(ICacheControl $cacheControl = null): ISqlPatternParser
    {
        if ($cacheControl === null) {
            return new SqlPatternParser();
        } else {
            return new CachingSqlPatternParser($cacheControl);
        }
    }

    public function createStatementExceptionFactory(): StatementExceptionFactory
    {
        return new StatementExceptionFactory();
    }

    public function createConnection(string $connName, ConnectionParameters $params): IConnection
    {
        $conn = new Connection($connName, $params);

        // register volatile type converters for SQL patterns
        $reg = $conn->getTypeRegister();
        $reg->registerSqlPatternType('rel', new RelationType($conn));
        $reg->registerSqlPatternType('cmd', new CommandType($conn));

        return $conn;
    }

    public function createCacheControl(IConnection $connection = null): ICacheControl
    {
        $prefix = 'Ivory' . Ivory::VERSION . '.';

        if ($connection === null) {
            $postfix = '';
        } else {
            $params = $connection->getParameters();
            $postfix = sprintf(
                '.%s.%d.%s',
                $params->getHost(),
                $params->getPort(),
                $params->getDbName()
            );
        }

        return new CacheControl(Ivory::getDefaultCacheImpl(), $prefix, $postfix);
    }
}

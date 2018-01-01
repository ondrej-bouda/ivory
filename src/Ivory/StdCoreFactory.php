<?php
declare(strict_types=1);
namespace Ivory;

use Ivory\Cache\CacheControl;
use Ivory\Cache\ICacheControl;
use Ivory\Connection\Connection;
use Ivory\Connection\ConnectionParameters;
use Ivory\Connection\IConnection;
use Ivory\Connection\IObservableTransactionControl;
use Ivory\Connection\ISessionControl;
use Ivory\Connection\IStatementExecution;
use Ivory\Connection\ITxHandle;
use Ivory\Connection\TxHandle;
use Ivory\Exception\StatementExceptionFactory;
use Ivory\Lang;
use Ivory\Lang\SqlPattern\CachingSqlPatternParser;
use Ivory\Lang\SqlPattern\ISqlPatternParser;
use Ivory\Lang\SqlPattern\SqlPatternParser;
use Ivory\Type\Ivory\CommandSerializer;
use Ivory\Type\Ivory\IdentifierSerializer;
use Ivory\Type\Ivory\LikeExpressionSerializer;
use Ivory\Type\Ivory\QuotedIdentifierSerializer;
use Ivory\Type\Ivory\RelationSerializer;
use Ivory\Type\Ivory\SqlSerializer;
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

        // standard value serializers
        $reg->registerValueSerializer('sql', new SqlSerializer());
        $reg->registerValueSerializer('ident', new IdentifierSerializer());
        $reg->registerValueSerializer('qident', new QuotedIdentifierSerializer());
        $reg->registerValueSerializer('rel', new RelationSerializer());
        $reg->registerValueSerializer('cmd', new CommandSerializer());
        $reg->registerValueSerializer('like', new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_NONE));
        $reg->registerValueSerializer('like_', new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_APPEND));
        $reg->registerValueSerializer('_like', new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_PREPEND));
        $reg->registerValueSerializer('_like_', new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_BOTH));

        // standard type abbreviations
        $reg->registerTypeAbbreviation('s', 'pg_catalog', 'text');
        $reg->registerTypeAbbreviation('i', 'pg_catalog', 'int8');
        $reg->registerTypeAbbreviation('num', 'pg_catalog', 'numeric');
        $reg->registerTypeAbbreviation('f', 'pg_catalog', 'float8');
        $reg->registerTypeAbbreviation('ts', 'pg_catalog', 'timestamp');
        $reg->registerTypeAbbreviation('tstz', 'pg_catalog', 'timestamptz');

        // standard type inference rules
        $reg->addTypeInferenceRule('int', 'pg_catalog', 'int8');
        $reg->addTypeInferenceRule('string', 'pg_catalog', 'text');
        $reg->addTypeInferenceRule('bool', 'pg_catalog', 'bool');
        $reg->addTypeInferenceRule('float', 'pg_catalog', 'float8');
        $reg->addTypeInferenceRule('null', 'pg_catalog', 'text');
        $reg->addTypeInferenceRule('array', 'pg_catalog', 'text[]');
        $reg->addTypeInferenceRule(Value\Decimal::class, 'pg_catalog', 'numeric');
        $reg->addTypeInferenceRule(Value\Date::class, 'pg_catalog', 'date');
        $reg->addTypeInferenceRule(Value\Time::class, 'pg_catalog', 'time');
        $reg->addTypeInferenceRule(Value\TimeTz::class, 'pg_catalog', 'timetz');
        $reg->addTypeInferenceRule(Value\Timestamp::class, 'pg_catalog', 'timestamp');
        $reg->addTypeInferenceRule(Value\TimestampTz::class, 'pg_catalog', 'timestamptz');
        $reg->addTypeInferenceRule(Value\TimeInterval::class, 'pg_catalog', 'interval');
        $reg->addTypeInferenceRule(Value\FixedBitString::class, 'pg_catalog', 'bit');
        $reg->addTypeInferenceRule(Value\VarBitString::class, 'pg_catalog', 'varbit');
        $reg->addTypeInferenceRule(Value\Json::class, 'pg_catalog', 'json');
        $reg->addTypeInferenceRule(Value\XmlContent::class, 'pg_catalog', 'xml');
        $reg->addTypeInferenceRule(Value\XmlDocument::class, 'pg_catalog', 'xml');
        $reg->addTypeInferenceRule(\DOMDocument::class, 'pg_catalog', 'xml');
        $reg->addTypeInferenceRule(\DOMNode::class, 'pg_catalog', 'xml');
        $reg->addTypeInferenceRule(\DOMNodeList::class, 'pg_catalog', 'xml');
        $reg->addTypeInferenceRule(\SimpleXMLElement::class, 'pg_catalog', 'xml');
        $reg->addTypeInferenceRule(Value\Point::class, 'pg_catalog', 'point');
        $reg->addTypeInferenceRule(Value\Line::class, 'pg_catalog', 'line');
        $reg->addTypeInferenceRule(Value\LineSegment::class, 'pg_catalog', 'lseg');
        $reg->addTypeInferenceRule(Value\Box::class, 'pg_catalog', 'box');
        $reg->addTypeInferenceRule(Value\Path::class, 'pg_catalog', 'path');
        $reg->addTypeInferenceRule(Value\Polygon::class, 'pg_catalog', 'polygon');
        $reg->addTypeInferenceRule(Value\Circle::class, 'pg_catalog', 'circle');
        $reg->addTypeInferenceRule(Value\NetAddress::class, 'pg_catalog', 'inet');
        $reg->addTypeInferenceRule(Value\MacAddr::class, 'pg_catalog', 'macaddr');
        $reg->addTypeInferenceRule(Value\Money::class, 'pg_catalog', 'money');
        $reg->addTypeInferenceRule(Value\PgLogSequenceNumber::class, 'pg_catalog', 'pg_lsn');
        $reg->addTypeInferenceRule(Value\TxIdSnapshot::class, 'pg_catalog', 'txid_snapshot');
        $reg->addTypeInferenceRule(Value\TextSearchVector::class, 'pg_catalog', 'tsvector');
        $reg->addTypeInferenceRule(Value\TextSearchQuery::class, 'pg_catalog', 'tsquery');

        return $reg;
    }

    public function createSqlPatternParser(?ICacheControl $cacheControl = null): ISqlPatternParser
    {
        if ($cacheControl !== null && $cacheControl->isCacheEnabled()) {
            return new CachingSqlPatternParser($cacheControl);
        } else {
            return new SqlPatternParser();
        }
    }

    public function createStatementExceptionFactory(?IStatementExecution $stmtExecution = null): StatementExceptionFactory
    {
        return new StatementExceptionFactory();
    }

    public function createConnection(string $connName, ConnectionParameters $params): IConnection
    {
        return new Connection($connName, $params);
    }

    public function createCacheControl(?IConnection $connection = null): ICacheControl
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

    public function createTransactionHandle(
        IStatementExecution $stmtExec,
        IObservableTransactionControl $observableTxCtl,
        ISessionControl $sessionCtl
    ): ITxHandle {
        return new TxHandle($stmtExec, $observableTxCtl, $sessionCtl);
    }
}

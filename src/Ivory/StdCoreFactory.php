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
use Ivory\Type\Std\StdTypeLoader;
use Ivory\Type\TypeRegister;
use Ivory\Value\Alg\ComparisonUtils;
use Ivory\Value\Alg\IValueComparator;

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
        $reg->registerTypeAbbreviations(Lang\Sql\Types::getReservedTypes());

        // standard value serializers
        $reg->registerValueSerializers([
            'sql' => new SqlSerializer(),
            'ident' => new IdentifierSerializer(),
            'qident' => new QuotedIdentifierSerializer(),
            'rel' => new RelationSerializer(),
            'cmd' => new CommandSerializer(),
            'like' => new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_NONE),
            'like_' => new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_APPEND),
            '_like' => new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_PREPEND),
            '_like_' => new LikeExpressionSerializer(LikeExpressionSerializer::WILDCARD_BOTH),
        ]);

        // standard type abbreviations
        $reg->registerTypeAbbreviations([
            's' => ['pg_catalog', 'text'],
            'i' => ['pg_catalog', 'int8'],
            'num' => ['pg_catalog', 'numeric'],
            'f' => ['pg_catalog', 'float8'],
            'ts' => ['pg_catalog', 'timestamp'],
            'tstz' => ['pg_catalog', 'timestamptz'],
        ]);

        // standard type inference rules
        $reg->registerTypeInferenceRules([
            'int' => ['pg_catalog', 'int8'],
            'string' => ['pg_catalog', 'text'],
            'bool' => ['pg_catalog', 'bool'],
            'float' => ['pg_catalog', 'float8'],
            'null' => ['pg_catalog', 'text'],
            'array' => ['pg_catalog', 'text[]'],
            Value\Decimal::class => ['pg_catalog', 'numeric'],
            Value\Date::class => ['pg_catalog', 'date'],
            Value\Time::class => ['pg_catalog', 'time'],
            Value\TimeTz::class => ['pg_catalog', 'timetz'],
            Value\Timestamp::class => ['pg_catalog', 'timestamp'],
            Value\TimestampTz::class => ['pg_catalog', 'timestamptz'],
            Value\TimeInterval::class => ['pg_catalog', 'interval'],
            Value\FixedBitString::class => ['pg_catalog', 'bit'],
            Value\VarBitString::class => ['pg_catalog', 'varbit'],
            Value\Json::class => ['pg_catalog', 'json'],
            Value\XmlContent::class => ['pg_catalog', 'xml'],
            Value\XmlDocument::class => ['pg_catalog', 'xml'],
            \DOMDocument::class => ['pg_catalog', 'xml'],
            \DOMNode::class => ['pg_catalog', 'xml'],
            \DOMNodeList::class => ['pg_catalog', 'xml'],
            \SimpleXMLElement::class => ['pg_catalog', 'xml'],
            Value\Point::class => ['pg_catalog', 'point'],
            Value\Line::class => ['pg_catalog', 'line'],
            Value\LineSegment::class => ['pg_catalog', 'lseg'],
            Value\Box::class => ['pg_catalog', 'box'],
            Value\Path::class => ['pg_catalog', 'path'],
            Value\Polygon::class => ['pg_catalog', 'polygon'],
            Value\Circle::class => ['pg_catalog', 'circle'],
            Value\NetAddress::class => ['pg_catalog', 'inet'],
            Value\MacAddr::class => ['pg_catalog', 'macaddr'],
            Value\MacAddr8::class => ['pg_catalog', 'macaddr8'],
            Value\Money::class => ['pg_catalog', 'money'],
            Value\PgLogSequenceNumber::class => ['pg_catalog', 'pg_lsn'],
            Value\TxIdSnapshot::class => ['pg_catalog', 'txid_snapshot'],
            Value\TextSearchVector::class => ['pg_catalog', 'tsvector'],
            Value\TextSearchQuery::class => ['pg_catalog', 'tsquery'],
        ]);

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

    public function createDefaultValueComparator(): IValueComparator
    {
        return new class implements IValueComparator
        {
            public function compareValues($a, $b): int
            {
                return ComparisonUtils::compareValues($a, $b);
            }
        };
    }
}

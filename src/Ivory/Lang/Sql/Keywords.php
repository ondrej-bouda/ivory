<?php
declare(strict_types=1);
namespace Ivory\Lang\Sql;

/**
 * PostgreSQL keywords.
 *
 * Classifies PostgreSQL keywords into four groups:
 * - reserved keywords: not allowed as identifiers, only allowed in the `AS` column label name;
 * - type/function names: reserved keywords which are allowed as type or function names;
 * - non-reserved keywords: unrestricted, may be freely used as identifiers without quoting;
 * - column names: non-reserved keywords which are not allowed as type or function names.
 *
 * @see https://github.com/postgres/postgres/blob/master/src/include/parser/kwlist.h
 */
class Keywords
{
    /**
     * Tells whether a given word is a non-reserved keyword.
     *
     * @param string $keyword
     * @return bool
     */
    public static function isUnreserved(string $keyword): bool
    {
        static $hash = [
            'abort' => true,
            'absolute' => true,
            'access' => true,
            'action' => true,
            'add' => true,
            'admin' => true,
            'after' => true,
            'aggregate' => true,
            'also' => true,
            'alter' => true,
            'always' => true,
            'assertion' => true,
            'assignment' => true,
            'at' => true,
            'attribute' => true,
            'backward' => true,
            'before' => true,
            'begin' => true,
            'by' => true,
            'cache' => true,
            'called' => true,
            'cascade' => true,
            'cascaded' => true,
            'catalog' => true,
            'chain' => true,
            'characteristics' => true,
            'checkpoint' => true,
            'class' => true,
            'close' => true,
            'cluster' => true,
            'comment' => true,
            'comments' => true,
            'commit' => true,
            'committed' => true,
            'configuration' => true,
            'conflict' => true,
            'connection' => true,
            'constraints' => true,
            'content' => true,
            'continue' => true,
            'conversion' => true,
            'copy' => true,
            'cost' => true,
            'csv' => true,
            'cube' => true,
            'current' => true,
            'cursor' => true,
            'cycle' => true,
            'data' => true,
            'database' => true,
            'day' => true,
            'deallocate' => true,
            'declare' => true,
            'defaults' => true,
            'deferred' => true,
            'definer' => true,
            'delete' => true,
            'delimiter' => true,
            'delimiters' => true,
            'dictionary' => true,
            'disable' => true,
            'discard' => true,
            'document' => true,
            'domain' => true,
            'double' => true,
            'drop' => true,
            'each' => true,
            'enable' => true,
            'encoding' => true,
            'encrypted' => true,
            'enum' => true,
            'escape' => true,
            'event' => true,
            'exclude' => true,
            'excluding' => true,
            'exclusive' => true,
            'execute' => true,
            'explain' => true,
            'extension' => true,
            'external' => true,
            'family' => true,
            'filter' => true,
            'first' => true,
            'following' => true,
            'force' => true,
            'forward' => true,
            'function' => true,
            'functions' => true,
            'global' => true,
            'granted' => true,
            'handler' => true,
            'header' => true,
            'hold' => true,
            'hour' => true,
            'identity' => true,
            'if' => true,
            'immediate' => true,
            'immutable' => true,
            'implicit' => true,
            'import' => true,
            'including' => true,
            'increment' => true,
            'index' => true,
            'indexes' => true,
            'inherit' => true,
            'inherits' => true,
            'inline' => true,
            'input' => true,
            'insensitive' => true,
            'insert' => true,
            'instead' => true,
            'invoker' => true,
            'isolation' => true,
            'key' => true,
            'label' => true,
            'language' => true,
            'large' => true,
            'last' => true,
            'leakproof' => true,
            'level' => true,
            'listen' => true,
            'load' => true,
            'local' => true,
            'location' => true,
            'lock' => true,
            'locked' => true,
            'logged' => true,
            'mapping' => true,
            'match' => true,
            'materialized' => true,
            'maxvalue' => true,
            'minute' => true,
            'minvalue' => true,
            'mode' => true,
            'month' => true,
            'move' => true,
            'name' => true,
            'names' => true,
            'next' => true,
            'no' => true,
            'nothing' => true,
            'notify' => true,
            'nowait' => true,
            'nulls' => true,
            'object' => true,
            'of' => true,
            'off' => true,
            'oids' => true,
            'operator' => true,
            'option' => true,
            'options' => true,
            'ordinality' => true,
            'over' => true,
            'owned' => true,
            'owner' => true,
            'parallel' => true,
            'parser' => true,
            'partial' => true,
            'partition' => true,
            'passing' => true,
            'password' => true,
            'plans' => true,
            'policy' => true,
            'preceding' => true,
            'prepare' => true,
            'prepared' => true,
            'preserve' => true,
            'prior' => true,
            'privileges' => true,
            'procedural' => true,
            'procedure' => true,
            'program' => true,
            'quote' => true,
            'range' => true,
            'read' => true,
            'reassign' => true,
            'recheck' => true,
            'recursive' => true,
            'ref' => true,
            'refresh' => true,
            'reindex' => true,
            'relative' => true,
            'release' => true,
            'rename' => true,
            'repeatable' => true,
            'replace' => true,
            'replica' => true,
            'reset' => true,
            'restart' => true,
            'restrict' => true,
            'returns' => true,
            'revoke' => true,
            'role' => true,
            'rollback' => true,
            'rollup' => true,
            'rows' => true,
            'rule' => true,
            'savepoint' => true,
            'schema' => true,
            'scroll' => true,
            'search' => true,
            'second' => true,
            'security' => true,
            'sequence' => true,
            'sequences' => true,
            'serializable' => true,
            'server' => true,
            'session' => true,
            'set' => true,
            'sets' => true,
            'share' => true,
            'show' => true,
            'simple' => true,
            'skip' => true,
            'snapshot' => true,
            'sql' => true,
            'stable' => true,
            'standalone' => true,
            'start' => true,
            'statement' => true,
            'statistics' => true,
            'stdin' => true,
            'stdout' => true,
            'storage' => true,
            'strict' => true,
            'strip' => true,
            'sysid' => true,
            'system' => true,
            'tables' => true,
            'tablespace' => true,
            'temp' => true,
            'template' => true,
            'temporary' => true,
            'text' => true,
            'transaction' => true,
            'transform' => true,
            'trigger' => true,
            'truncate' => true,
            'trusted' => true,
            'type' => true,
            'types' => true,
            'unbounded' => true,
            'uncommitted' => true,
            'unencrypted' => true,
            'unknown' => true,
            'unlisten' => true,
            'unlogged' => true,
            'until' => true,
            'update' => true,
            'vacuum' => true,
            'valid' => true,
            'validate' => true,
            'validator' => true,
            'value' => true,
            'varying' => true,
            'version' => true,
            'view' => true,
            'views' => true,
            'volatile' => true,
            'whitespace' => true,
            'within' => true,
            'without' => true,
            'work' => true,
            'wrapper' => true,
            'write' => true,
            'xml' => true,
            'year' => true,
            'yes' => true,
            'zone' => true,
        ];
        return isset($hash[strtolower($keyword)]);
    }

    /**
     * Tells whether a given word is a non-reserved keyword, although it cannot be used as a function or type name.
     *
     * @param string $keyword
     * @return bool
     */
    public static function isColName(string $keyword): bool
    {
        static $hash = [
            'between' => true,
            'bigint' => true,
            'bit' => true,
            'boolean' => true,
            'char' => true,
            'character' => true,
            'coalesce' => true,
            'dec' => true,
            'decimal' => true,
            'exists' => true,
            'extract' => true,
            'float' => true,
            'greatest' => true,
            'grouping' => true,
            'inout' => true,
            'int' => true,
            'integer' => true,
            'interval' => true,
            'least' => true,
            'national' => true,
            'nchar' => true,
            'none' => true,
            'nullif' => true,
            'numeric' => true,
            'out' => true,
            'overlay' => true,
            'position' => true,
            'precision' => true,
            'real' => true,
            'row' => true,
            'setof' => true,
            'smallint' => true,
            'substring' => true,
            'time' => true,
            'timestamp' => true,
            'treat' => true,
            'trim' => true,
            'values' => true,
            'varchar' => true,
            'xmlattributes' => true,
            'xmlconcat' => true,
            'xmlelement' => true,
            'xmlexists' => true,
            'xmlforest' => true,
            'xmlparse' => true,
            'xmlpi' => true,
            'xmlroot' => true,
            'xmlserialize' => true,
        ];
        return isset($hash[strtolower($keyword)]);
    }

    /**
     * Tells whether a given word is a reserved keyword, although may be used as a function or type name.
     *
     * @param string $keyword
     * @return bool
     */
    public static function isTypeOrFuncName(string $keyword): bool
    {
        static $hash = [
            'authorization' => true,
            'binary' => true,
            'collation' => true,
            'concurrently' => true,
            'cross' => true,
            'current_schema' => true,
            'freeze' => true,
            'full' => true,
            'ilike' => true,
            'inner' => true,
            'is' => true,
            'isnull' => true,
            'join' => true,
            'left' => true,
            'like' => true,
            'natural' => true,
            'notnull' => true,
            'outer' => true,
            'overlaps' => true,
            'right' => true,
            'similar' => true,
            'tablesample' => true,
            'verbose' => true,
        ];
        return isset($hash[strtolower($keyword)]);
    }

    /**
     * Tells whether a given word is a reserved keyword.
     *
     * @param string $keyword
     * @return bool
     */
    public static function isReserved(string $keyword): bool
    {
        $hash = [
            'all' => true,
            'analyse' => true,
            'analyze' => true,
            'and' => true,
            'any' => true,
            'array' => true,
            'as' => true,
            'asc' => true,
            'asymmetric' => true,
            'both' => true,
            'case' => true,
            'cast' => true,
            'check' => true,
            'collate' => true,
            'column' => true,
            'constraint' => true,
            'create' => true,
            'current_catalog' => true,
            'current_date' => true,
            'current_role' => true,
            'current_time' => true,
            'current_timestamp' => true,
            'current_user' => true,
            'default' => true,
            'deferrable' => true,
            'desc' => true,
            'distinct' => true,
            'do' => true,
            'else' => true,
            'end' => true,
            'except' => true,
            'false' => true,
            'fetch' => true,
            'for' => true,
            'foreign' => true,
            'from' => true,
            'grant' => true,
            'group' => true,
            'having' => true,
            'in' => true,
            'initially' => true,
            'intersect' => true,
            'into' => true,
            'lateral' => true,
            'leading' => true,
            'limit' => true,
            'localtime' => true,
            'localtimestamp' => true,
            'not' => true,
            'null' => true,
            'offset' => true,
            'on' => true,
            'only' => true,
            'or' => true,
            'order' => true,
            'placing' => true,
            'primary' => true,
            'references' => true,
            'returning' => true,
            'select' => true,
            'session_user' => true,
            'some' => true,
            'symmetric' => true,
            'table' => true,
            'then' => true,
            'to' => true,
            'trailing' => true,
            'true' => true,
            'union' => true,
            'unique' => true,
            'user' => true,
            'using' => true,
            'variadic' => true,
            'when' => true,
            'where' => true,
            'window' => true,
            'with' => true,
        ];
        return isset($hash[strtolower($keyword)]);
    }
}

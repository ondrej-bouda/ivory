<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Connection\IConnection;
use Ivory\Exception\InvalidStateException;
use Ivory\Ivory;
use Ivory\Query\SqlPatternDefinitionMacros;
use Ivory\Type\ConnectionDependentObject;
use Ivory\Type\IConnectionDependentObject;
use Ivory\Type\IValueSerializer;

/**
 * Relation built from an array of rows.
 *
 * Immutable - once constructed, the data cannot be changed.
 */
class ArrayRelation extends RelationBase implements IConnectionDependentObject
{
    use ConnectionDependentObject {
        attachToConnection as traitAttachToConnection;
        detachFromConnection as traitDetachFromConnection;
    }

    private $typeMap;
    private $columns = null;
    private $colNameMap;
    private $rows;
    private $numRows;

    public function __sleep()
    {
        return ['typeMap', 'rows'];
    }

    public function __wakeup()
    {
        $this->init();
    }

    /**
     * Creates an array relation from a list of rows.
     *
     * Each row is interpreted as a map of column names to the corresponding values. Ideally, all rows should have the
     * same structure, although that is not really required:
     * - missing values are treated as `null` values,
     * - extra items are ignored,
     * - mutual order of the map entries is insignificant (except for the first row in case `$typeMap` is not given -
     *   see below).
     *
     * The relation columns may optionally be defined using the second parameter `$typeMap`. It is an ordered map of
     * column names to the specification of their types, which may either be:
     * - an {@link IType} or {@link IValueSerializer} object, or
     * - a string type specification (name or alias) as it would be used in an {@link SqlPattern} after the `%` sign,
     * - `null` to let the type dictionary infer the type automatically (more details below).
     *
     * If given, the `$typeMap` serves as the column definition list. Columns not mentioned in `$typeMap` are ignored in
     * `$rows`. Also, the order of relation columns is determined by `$typeMap`.
     *
     * If `$typeMap` is not given (or given as `null`), *the first row* from `$rows` takes the role of the relation
     * column definer, and the type of each column will be inferred automatically just as its type specification was
     * given as `null`.
     *
     * Note that there is no problem in using integers for column names. Actually, plain PHP lists (i.e., arrays with no
     * explicit keys specified) may be used, both for `$rows` and `$typeMap`.
     *
     * When inferring the type, the first non-`null` value of the column is used. If only `null`s are present in the
     * column, the type registered for `null` values is requested from the type dictionary. The autodetection is
     * performed using the type dictionary used by the connection which this relation gets attached to.
     *
     * @param array $rows list of data rows, each a map of column names to values
     * @param array|null $typeMap optional map specifying columns mutual order and types
     * @return ArrayRelation
     */
    public static function fromRows(array $rows, ?array $typeMap = null): ArrayRelation
    {
        if ($typeMap === null) {
            $typeMap = [];
            $firstRow = reset($rows);
            if ($firstRow !== false) { // in case no rows are there, we can infer nothing than an empty list of columns
                foreach ($firstRow as $columnName => $_) {
                    $typeMap[$columnName] = null;
                }
            }
        }

        return new ArrayRelation($rows, $typeMap);
    }

    /**
     * @param array $rows list: map: string column name => value
     * @param array $typeMap ordered map: column name => type specifier: {@link IType}, <tt>string</tt> or <tt>null</tt>
     */
    protected function __construct(array $rows, array $typeMap)
    {
        parent::__construct();

        $this->typeMap = $typeMap;
        $this->rows = $rows;
        $this->init();
    }

    private function init()
    {
        $this->numRows = count($this->rows);
        $this->colNameMap = array_flip(array_keys($this->typeMap));
    }

    public function attachToConnection(IConnection $connection): void
    {
        $this->traitAttachToConnection($connection);
        $this->buildColumns();
    }

    private function buildColumns(): void
    {
        $types = $this->inferTypes();

        $this->columns = [];
        foreach ($this->typeMap as $colName => $_) {
            $colOffset = count($this->columns);
            $col = new Column($this, $colOffset, (string)$colName, $types[$colName]);
            $this->columns[] = $col;
        }
    }

    /**
     * @return IValueSerializer[] unordered map: column name => type converter
     */
    private function inferTypes(): array
    {
        $result = [];
        $toParse = [];
        $toInfer = [];
        foreach ($this->typeMap as $colName => $typeSpec) {
            if ($typeSpec instanceof IValueSerializer) {
                $result[$colName] = $typeSpec;
            } elseif (is_string($typeSpec)) {
                $toParse[$colName] = $typeSpec;
            } elseif ($typeSpec === null) {
                $toInfer[$colName] = true;
            } else {
                throw new \UnexpectedValueException(
                    'Unexpected kind of column type specification: ' . get_class($typeSpec)
                );
            }
        }

        if ($toParse) {
            $typeDictionary = $this->getConnection()->getTypeDictionary();
            $parser = Ivory::getSqlPatternParser();
            foreach ($toParse as $colName => $typeSpecStr) {
                $pattern = $parser->parse('%' . $typeSpecStr);
                $placeholder = $pattern->getPositionalPlaceholders()[0];
                $result[$colName] = SqlPatternDefinitionMacros::getReferencedSerializer($placeholder, $typeDictionary);
            }
        }

        if ($toInfer) {
            $typeDictionary = $this->getConnection()->getTypeDictionary();
            foreach ($this->rows as $row) {
                foreach ($toInfer as $colName => $_) {
                    if (isset($row[$colName])) {
                        $result[$colName] = $typeDictionary->requireTypeByValue($row[$colName]);
                        unset($toInfer[$colName]);
                    }
                }

                if (!$toInfer) {
                    break;
                }
            }
            foreach ($toInfer as $colName => $_) {
                $result[$colName] = $typeDictionary->requireTypeByValue(null);
            }
        }

        return $result;
    }

    public function detachFromConnection(): void
    {
        $this->traitDetachFromConnection();
        $this->columns = null;
    }

    public function getColumns(): array
    {
        if ($this->columns === null) {
            throw new InvalidStateException('The relation has not been attached to any connection');
        }
        return $this->columns;
    }

    public function tuple(int $offset = 0): ITuple
    {
        if ($offset >= $this->numRows || $offset < -$this->numRows) {
            throw new \OutOfBoundsException("Offset $offset is out of the result bounds [0,{$this->numRows})");
        }

        $effectiveOffset = ($offset >= 0 ? $offset : $this->numRows + $offset);
        if (!isset($this->rows[$effectiveOffset])) {
            throw new \RuntimeException("Error fetching row at offset $offset");
        }
        $row = $this->rows[$effectiveOffset];

        $data = [];
        foreach ($this->colNameMap as $colName => $_) {
            $data[] = ($row[$colName] ?? null);
        }

        return new Tuple($data, $this->colNameMap);
    }

    public function count()
    {
        return $this->numRows;
    }
}

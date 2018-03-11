<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Type\ConnectionDependentObject;
use Ivory\Type\IConnectionDependentObject;

/**
 * Relation built from an array of rows.
 *
 * Immutable - once constructed, the data cannot be changed.
 */
class ArrayRelation extends RelationBase implements IConnectionDependentObject
{
    use ConnectionDependentObject {
        detachFromConnection as traitDetachFromConnection;
    }

    private $columns = null;
    private $colNameMap;
    private $rows;
    private $numRows;

    /**
     * Creates an array relation from a list of rows, inferring the types of columns automatically from the values.
     *
     * The type of each column is inferred from the first non-`null` value of the column. If only `null`s are present in
     * the column, the type registered for `null` values is requested from the type dictionary. The autodetection is
     * performed using the type dictionary used by the connection which this relation gets attached to.
     *
     * @param array $rows list of data rows, each a map of column names to values
     * @return ArrayRelation
     */
    public static function createAutodetected(array $rows): ArrayRelation
    {
        return new ArrayRelation($rows);
    }

    /**
     * @param array $rows list: map: string column name => value
     */
    protected function __construct(array $rows)
    {
        parent::__construct();

        $this->rows = $rows;
        $this->numRows = count($rows);
        $this->buildColNameMap();
    }

    private function buildColNameMap()
    {
        $this->colNameMap = [];

        $firstRow = reset($this->rows);
        if ($firstRow === false) {
            return; // no rows - no column names to map
        }

        $colOffset = 0;
        foreach ($firstRow as $colName => $_) {
            $this->colNameMap[$colName] = $colOffset;
            $colOffset++;
        }
    }

    public function getColumns(): array
    {
        if ($this->columns === null) {
            $this->buildColumns();
        }
        return $this->columns;
    }

    private function buildColumns(): void
    {
        $types = $this->inferTypeMap();

        $this->columns = [];
        foreach ($types as $colName => $type) {
            $colOffset = count($this->columns);
            $col = new Column($this, $colOffset, (string)$colName, $type);
            $this->columns[] = $col;
        }
    }

    private function inferTypeMap(): array
    {
        $remaining = reset($this->rows);
        if ($remaining === false) {
            return []; // no rows - cannot infer types of columns
        }
        $dataTypes = array_fill_keys(array_keys($remaining), null);
        $typeDictionary = $this->getConnection()->getTypeDictionary();
        foreach ($this->rows as $row) {
            foreach ($remaining as $colName => $_) {
                $val = ($row[$colName] ?? null);
                if ($val !== null) {
                    $dataTypes[$colName] = $typeDictionary->requireTypeByValue($val);
                    unset($remaining[$colName]);
                }
            }

            if (!$remaining) {
                break;
            }
        }

        foreach ($remaining as $colName => $_) {
            $dataTypes[$colName] = $typeDictionary->requireTypeByValue(null);
        }

        return $dataTypes;
    }

    public function detachFromConnection(): void
    {
        $this->traitDetachFromConnection();
        $this->columns = null;
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

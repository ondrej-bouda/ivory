<?php
namespace Ivory\Relation;

use Ivory\Type\IType;
use Ivory\Type\ITypeDictionary;

/**
 * Relation built from an array of rows.
 *
 * Immutable - once constructed, the data cannot be changed.
 */
class ArrayRelation extends RelationBase
{
    private $cols;
    private $colNameMap;
    private $rows;
    private $numRows;

    public static function createAutodetect(array $rows, ITypeDictionary $typeDictionary): ArrayRelation
    {
        if (!$rows) {
            return new ArrayRelation([], []);
        }

        $remaining = reset($rows);
        if (!is_array($remaining)) {
            throw new \InvalidArgumentException('$rows');
        }
        $dataTypes = array_fill_keys(array_keys($remaining), null);
        foreach ($rows as $row) {
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

        return new ArrayRelation($rows, $dataTypes);
    }

    /**
     * @param array $rows list: map: column name => value
     * @param IType[] $dataTypes ordered map: column name => type
     */
    protected function __construct(array $rows, array $dataTypes)
    {
        parent::__construct();

        $this->rows = $rows;
        $this->numRows = count($rows);
        $this->buildCols($dataTypes);
    }

    private function buildCols(array $dataTypes): void
    {
        $this->cols = [];
        $this->colNameMap = [];

        foreach ($dataTypes as $colName => $type) {
            $colOffset = count($this->cols);
            $col = new Column($this, $colOffset, $colName, $type);
            $this->cols[] = $col;
            $this->colNameMap[$colName] = $colOffset;
        }
    }

    public function getColumns(): array
    {
        return $this->cols;
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

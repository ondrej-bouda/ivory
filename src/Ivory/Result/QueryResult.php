<?php
declare(strict_types=1);
namespace Ivory\Result;

use Ivory\Connection\ITypeControl;
use Ivory\Exception\NotImplementedException;
use Ivory\Exception\InvalidResultException;
use Ivory\Relation\Column;
use Ivory\Relation\FilteredRelation;
use Ivory\Relation\IRelation;
use Ivory\Relation\ITuple;
use Ivory\Relation\ProjectedRelation;
use Ivory\Relation\RelationMacros;
use Ivory\Relation\RenamedRelation;
use Ivory\Relation\Tuple;
use Ivory\Type\IType;

class QueryResult extends Result implements IQueryResult
{
    use RelationMacros;

    private $numRows;
    /** @var Column[] */
    private $columns;
    /** @var array map: column name => offset of the first column of the name, or {@link Tuple::AMBIGUOUS_COL} */
    private $colNameMap;
    /** @var IType[] list of column type converters; $this->columns could be used, but this is faster */
    private $colTypes;


    /**
     * @param resource $resultHandler the result, with the internal pointer at the beginning
     * @param ITypeControl $typeControl
     * @param string|null $lastNotice last notice captured on the connection
     */
    public function __construct($resultHandler, ITypeControl $typeControl, ?string $lastNotice = null)
    {
        parent::__construct($resultHandler, $lastNotice);

        $this->numRows = $this->fetchNumRows();
        $this->initCols($typeControl);
    }

    private function fetchNumRows(): int
    {
        $numRows = pg_num_rows($this->handler);
        if ($numRows >= 0 && $numRows !== null) { // NOTE: besides -1, pg_num_rows() might return NULL on error
            return $numRows;
        } else {
            throw new InvalidResultException('Error retrieving number of rows of the result.');
        }
    }

    private function initCols(ITypeControl $typeControl): void
    {
        $numFields = pg_num_fields($this->handler);
        if ($numFields < 0 || $numFields === null) {
            throw new InvalidResultException('Error retrieving number of fields of the result.');
        }
        $this->columns = [];
        $this->colNameMap = [];
        $this->colTypes = [];
        for ($i = 0; $i < $numFields; $i++) {
            /* NOTE: pg_field_type() cannot be used for simplicity - multiple types of the same name might exist in
             *       different schemas. Thus, the only reasonable way to recognize the types is using their OIDs,
             *       returned by pg_field_type_oid(). Up to some extreme cases, within a given database, the same OID
             *       will always refer to the same data type.
             */
            $name = pg_field_name($this->handler, $i);
            if ($name === false || $name === null) { // NOTE: besides false, pg_field_name() might return NULL on error
                throw new InvalidResultException("Error retrieving name of result column $i.");
            }
            if ($name == '?column?') {
                $name = null;
            }
            $typeOid = pg_field_type_oid($this->handler, $i);
            // NOTE: besides false, pg_field_type_oid() might return NULL on error
            if ($typeOid === false || $typeOid === null) {
                throw new InvalidResultException("Error retrieving type OID of result column $i.");
            }
            // NOTE: the type dictionary may change during the iterations, so take a fresh one every time
            $typeDictionary = $typeControl->getTypeDictionary();
            $type = $typeDictionary->requireTypeByOid($typeOid);

            $this->columns[] = new Column($this, $i, $name, $type);
            if ($name !== null) {
                $this->colNameMap[$name] = (isset($this->colNameMap[$name]) ? Tuple::AMBIGUOUS_COL : $i);
            }

            $this->colTypes[] = $type;
        }
    }


    //region IRelation

    public function getColumns(): array
    {
        return $this->columns;
    }

    public function filter($decider): IRelation
    {
        return new FilteredRelation($this, $decider);
    }

    public function project($columns): IRelation
    {
        return new ProjectedRelation($this, $columns);
    }

    public function rename(iterable $renamePairs): IRelation
    {
        return new RenamedRelation($this, $renamePairs);
    }

    public function uniq($hasher = null, $equalizer = null): IRelation
    {
        throw new NotImplementedException();
    }

    public function tuple(int $offset = 0): ITuple
    {
        if ($offset >= 0) {
            if ($offset >= $this->numRows) {
                throw new \OutOfBoundsException("Offset $offset is out of the result bounds [0,{$this->numRows})");
            }
            $rawData = pg_fetch_row($this->handler, $offset);
        } else {
            if ($offset < -$this->numRows) {
                throw new \OutOfBoundsException("Offset $offset is out of the result bounds [0,{$this->numRows})");
            }
            $rawData = pg_fetch_row($this->handler, $this->numRows + $offset);
        }

        /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection pg_fetch_row() may return FALSE */
        if ($rawData === false || $rawData === null) {
            throw new InvalidResultException("Error fetching row at offset $offset");
        }

        $data = [];
        foreach ($this->colTypes as $i => $type) {
            $v = $rawData[$i];
            $data[$i] = ($v !== null ? $type->parseValue($v) : null);
        }

        return new Tuple($data, $this->colNameMap);
    }

    //endregion

    //region \Countable

    public function count(): int
    {
        return $this->numRows;
    }

    //endregion
}

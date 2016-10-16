<?php
namespace Ivory\Relation;

use Ivory\Exception\NotImplementedException;
use Ivory\Relation\Mapping\ArrayTupleMap;

abstract class RelationBase implements \IteratorAggregate, IRelation
{
    use RelationMacros;


    public function __construct()
    {
    }


    public function filter($decider)
    {
        return new FilteredRelation($this, $decider);
    }

    public function project($columns)
    {
        return new ProjectedRelation($this, $columns);
    }

    public function rename($renamePairs)
    {
        return new RenamedRelation($this, $renamePairs);
    }

    public function map(...$mappingCols)
    {
        if (!$mappingCols) {
            throw new \InvalidArgumentException('no $mappingCols');
        }

        $multiDimCols = array_slice($mappingCols, 0, -1);
        $lastCol = $mappingCols[count($mappingCols) - 1];

        // FIXME: depending on the data type of the key, either use an array-based implementation, or an object hashing implementation
        $map = new ArrayTupleMap();
        foreach ($this as $tuple) {
            /** @var ITuple $tuple */
            $m = $map;
            foreach ($multiDimCols as $col) {
                $key = $tuple->value($col);
                if (!isset($m[$key])) {
                    $m->put($key, new ArrayTupleMap());
                }
                $m = $m[$key];
            }

            $key = $tuple->value($lastCol);
            $added = $m->putIfNotExists($key, $tuple);

            if (!$added) {
                $keys = [];
                foreach ($multiDimCols as $col) {
                    $keys[] = $tuple->value($col);
                }
                $keys[] = $tuple->value($lastCol);
                $keyDesc = implode(', ', $keys);
                trigger_error("Duplicate entry under key ($keyDesc). Skipping.", E_USER_WARNING);
            }
        }

        return $map;
    }

    public function multimap(...$mappingCols)
    {
        throw new NotImplementedException();
    }

    public function assoc(...$cols)
    {
        throw new NotImplementedException();
    }

    public function uniq($hasher = null, $comparator = null)
    {
        throw new NotImplementedException();
    }
}

<?php
namespace Ivory\Relation;

use Ivory\Exception\InternalException;
use Ivory\Exception\UndefinedColumnException;
use Ivory\Relation\Alg\ITupleEvaluator;

class ProjectedRelation extends ProjectedRelationBase
{
    /** @var array list: either index to the source tuple data to take, or a tuple evaluator */
    private $projectionList;

    public function __construct(IRelation $source, $colDef)
    {
        parent::__construct($source, $this->defineColumns($source, $colDef));
    }

    private function defineColumns(IRelation $source, $colDef)
    {
        $srcCols = $source->getColumns();

        $columns = [];
        $this->projectionList = [];

        foreach ($colDef as $key => $value) {
            $nameSpecified = (filter_var($key, FILTER_VALIDATE_INT) === false || is_object($value));
            $colName = ($nameSpecified ? $key : (isset($srcCols[$value]) ? $srcCols[$value]->getName() : $value));

            if (filter_var($value, FILTER_VALIDATE_INT) !== false) { // column offset
                if (!isset($srcCols[$value])) {
                    throw new UndefinedColumnException($value);
                }
                $columns[] = new Column($this, count($this->projectionList), $colName, $srcCols[$value]->getType());
                $this->projectionList[] = (int)$value;
            } elseif (is_string($value)) { // column name
                if ($value[0] == '/') { // PCRE macro
                    $pcre = $value;
                    $matchAll = true;
                    $repl = ($nameSpecified ? $key : null);
                } else {
                    $pcre = self::simpleMacroPatternToPcre($value, $starCnt);
                    $matchAll = ($starCnt > 0);
                    $repl = ($nameSpecified ? self::simpleMacroReplacementToPcre($key) : null);
                }
                if ($repl === null) {
                    $cns = [];
                    foreach ($srcCols as $i => $c) {
                        $name = $c->getName();
                        if ($name !== null) {
                            $cns[$i] = $name;
                        }
                    }
                    $matched = preg_grep($pcre, $cns);
                    if (!$matchAll) {
                        $matched = array_slice($matched, 0, 1, true);
                    }
                } else {
                    $matched = [];
                    foreach ($srcCols as $i => $c) {
                        if ($c->getName() !== null) {
                            $newName = preg_replace($pcre, $repl, $c->getName(), 1, $cnt);
                            if ($cnt) {
                                $matched[$i] = $newName;
                                if (!$matchAll) {
                                    break;
                                }
                            }
                        }
                    }
                }
                if (!$matched) {
                    throw new UndefinedColumnException($value);
                }
                foreach ($matched as $i => $cn) {
                    $columns[] = new Column($this, count($this->projectionList), $cn, $srcCols[$i]->getType());
                    $this->projectionList[] = $i;
                }
            } elseif ($value instanceof ITupleEvaluator || $value instanceof \Closure) {
                $columns[] = new Column($this, $value, $colName, null);
                $this->projectionList[] = $value;
            } else {
                throw new \InvalidArgumentException("Invalid specification of the projection item '$key'");
            }
        }

        return $columns;
    }

    public function tuple(int $offset = 0): ITuple
    {
        $srcTuple = parent::tuple($offset);

        $data = [];
        foreach ($this->projectionList as $i => $spec) {
            if (is_int($spec)) {
                $data[$i] = $srcTuple[$spec];
            } elseif ($spec instanceof ITupleEvaluator) {
                $data[$i] = $spec->evaluate($srcTuple);
            } elseif ($spec instanceof \Closure) {
                $data[$i] = call_user_func($spec, $srcTuple);
            } else {
                throw new InternalException("The type of projection list item $i is not supported");
            }
        }

        return new Tuple($data, $this->getColNames(), $this->getColNameMap());
    }
}

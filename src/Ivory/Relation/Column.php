<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Type\IValueSerializer;
use Ivory\Value\Alg\CallbackValueEqualizer;
use Ivory\Value\Alg\CallbackValueHasher;
use Ivory\Value\Alg\ITupleEvaluator;
use Ivory\Value\Alg\IValueEqualizer;
use Ivory\Value\Alg\IValueHasher;
use Ivory\Value\Alg\ComparisonUtils;

class Column implements \IteratorAggregate, IColumn
{
    private $relation;
    private $colDef;
    private $name;
    private $type;


    /**
     * @param IRelation $relation relation the column is a part of
     * @param int|string|ITupleEvaluator|\Closure $colDef offset or tuple evaluator of the column within the relation
     * @param string|null $name name of the column, or <tt>null</tt> if not named
     * @param IValueSerializer|null $type type of the column values, or just a serializer to SQL, or <tt>null</tt> if
     *                                    this is a computed column without explicit type specification
     */
    public function __construct(IRelation $relation, $colDef, ?string $name, ?IValueSerializer $type)
    {
        $this->relation = $relation;
        $this->colDef = $colDef;
        $this->name = $name;
        $this->type = $type;
    }


    public function getName(): ?string
    {
        return $this->name;
    }

    public function getType(): ?IValueSerializer
    {
        return $this->type;
    }

    public function renameTo(string $newName): IColumn
    {
        return new Column($this->relation, $this->colDef, $newName, $this->type);
    }

    public function bindToRelation(IRelation $relation): IColumn
    {
        return new Column($relation, $this->colDef, $this->name, $this->type);
    }

    public function filter($decider): IColumn
    {
        return new FilteredColumn($this, $decider);
    }

    public function uniq($hasher = null, $equalizer = null): IColumn
    {
        if ($hasher === null) {
            $hasher = new CallbackValueHasher(function ($value) {
                return (is_int($value) || is_string($value) ? $value : serialize($value));
            });
        } elseif ($hasher === 1) {
            $hasher = new CallbackValueHasher(function (/** @noinspection PhpUnusedParameterInspection */ $value) {
                return 1;
            });
        } elseif (!$hasher instanceof IValueHasher) {
            if (is_callable($hasher)) {
                $hasher = new CallbackValueHasher($hasher);
            } else {
                throw new \InvalidArgumentException('$hasher');
            }
        }

        if ($equalizer === null) {
            $equalizer = new CallbackValueEqualizer(function ($a, $b) {
                return ComparisonUtils::equals($a, $b);
            });
        } elseif (!$equalizer instanceof IValueEqualizer) {
            $equalizer = new CallbackValueEqualizer($equalizer);
        }

        $hashTable = [];
        return new FilteredColumn($this, function ($value) use ($hasher, $equalizer, &$hashTable) {
            $h = $hasher->hash($value);
            if (!isset($hashTable[$h])) {
                $hashTable[$h] = [$value];
                return true;
            } else {
                foreach ($hashTable[$h] as $v) {
                    if ($equalizer->equal($value, $v)) {
                        return false;
                    }
                }
                $hashTable[$h][] = $value;
                return true;
            }
        });
    }

    public function toArray(): array
    {
        return iterator_to_array($this, false);
    }

    public function value(int $valueOffset = 0)
    {
        return $this->relation->value($this->colDef, $valueOffset);
    }

    //region Countable

    public function count()
    {
        return $this->relation->count();
    }

    //endregion

    //region IteratorAggregate

    public function getIterator()
    {
        return new ColumnIterator($this);
    }

    //endregion
}

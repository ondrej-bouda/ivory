<?php
namespace Ivory\Type\Ivory;

use Ivory\Query\IRelationRecipe;
use Ivory\Relation\IRelation;
use Ivory\Relation\ITuple;
use Ivory\Type\INamedType;
use Ivory\Type\IType;
use Ivory\Utils\StringUtils;

/**
 * Internal Ivory converter serializing relation and relation recipes into SQL value.
 *
 * Used in SQL patterns to handle `%rel` placeholders. These will typically be used in subselects or in
 * {@link https://www.postgresql.org/docs/current/static/queries-with.html `WITH` queries (common table expressions)}.
 *
 * Note that an {@link \InvalidArgumentException} is thrown when serializing `null` as that clearly signifies an error.
 */
class RelationType extends VolatilePatternTypeBase
{
    private $identifierType = null;

    public function serializeValue($val): string
    {
        if ($val instanceof IRelationRecipe) {
            $typeDictionary = $this->getConnection()->getTypeDictionary();
            return $val->toSql($typeDictionary);
        } elseif ($val instanceof IRelation) {
            return $this->serializeRelation($val);
        } else {
            $recognizedClasses = [IRelationRecipe::class, IRelation::class];
            throw new \InvalidArgumentException('Expecting an ' . implode(' or ', $recognizedClasses) . ' object');
        }
    }

    private function serializeRelation(IRelation $rel): string
    {
        $columns = $this->recognizeRelationColumns($rel);
        // Due to SQL limitations, we must distinguish special cases and provide a different SQL query for them.
        if (!$columns) {
            return $this->serializeNoColumnRelation($rel->count());
        } elseif ($rel->count() == 0) {
            return $this->serializeEmptyRelation($columns);
        } else {
            return $this->serializeNonEmptyRelation($rel, $columns);
        }
    }

    /**
     * @param IRelation $relation
     * @return array list: pair (column name, type converter)
     */
    private function recognizeRelationColumns(IRelation $relation): array
    {
        $columns = [];
        foreach ($relation->getColumns() as $i => $column) {
            $colName = ($column->getName() ?? 'column' . ($i + 1));
            $type = $column->getType();
            if ($type === null) {
                $colIdent = ($column->getName() !== null ?
                    'column ' . $column->getName() :
                    StringUtils::englishOrd($i+1) . ' column'
                );
                throw new \InvalidArgumentException("Invalid relation for serialization - $colIdent has unknown type");
            }
            $columns[] = [$colName, $type];
        }
        return $columns;
    }

    /**
     * @param int $rowCount number of rows for the relation to consist of (even multiple rows as possible for relation
     *                        of no columns in PostgreSQL)
     * @return string SQL query the result of which is a relation with no columns and the given number of rows
     */
    private function serializeNoColumnRelation(int $rowCount): string
    {
        $result = 'SELECT';
        if ($rowCount == 0) {
            $result .= ' WHERE FALSE';
        } else {
            $result .= str_repeat(' UNION ALL SELECT', $rowCount - 1);
        }
        return $result;
    }

    /**
     * @param array $columns result of {@link recognizeRelationColumns()}
     * @return string SQL query the result of which is a relation the specified columns and no rows
     */
    private function serializeEmptyRelation(array $columns): string
    {
        $identType = $this->getIdentifierType();
        $result = 'SELECT';
        foreach ($columns as $i => list($colName, $type)) {
            $result .= ($i == 0 ? ' ' : ', ') . 'NULL';
            if ($type instanceof INamedType) {
                $result .= "::{$type->getSchemaName()}.{$type->getName()}";
            }
            $result .= ' AS ' . $identType->serializeValue($colName);
        }
        $result .= ' WHERE FALSE';
        return $result;
    }

    private function serializeNonEmptyRelation(IRelation $rel, array $columns): string
    {
        $result = <<<VAL
SELECT *
FROM (
  VALUES 
VAL;
        $isFirstTuple = true;
        foreach ($rel as $tuple) {
            /** @var ITuple $tuple */
            if (!$isFirstTuple) {
                $result .= ',' . PHP_EOL . '         ';
            }
            $result .= '(';
            $colIdx = 0;
            foreach ($tuple as $val) {
                if ($colIdx > 0) {
                    $result .= ', ';
                }
                /** @var IType $type */
                $type = $columns[$colIdx][1];
                $result .= $type->serializeValue($val);
                if ($isFirstTuple && $type instanceof INamedType) {
                    $result .= "::{$type->getSchemaName()}.{$type->getName()}";
                }
                $colIdx++;
            }
            $result .= ')';
            $isFirstTuple = false;
        }
        $result .= <<<SQL

) t (
SQL;
        $identType = $this->getIdentifierType();
        foreach ($columns as $i => list($name, $type)) {
            if ($i > 0) {
                $result .= ', ';
            }
            $result .= $identType->serializeValue($name);
        }
        $result .= ')';

        return $result;
    }

    private function getIdentifierType(): IdentifierType
    {
        if ($this->identifierType === null) {
            $this->identifierType = new IdentifierType();
        }
        return $this->identifierType;
    }
}

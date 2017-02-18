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
        /** @var string[] $colNames list of column names */
        $colNames = [];
        /** @var IType[] $typeConverters list of type converters, one for each relation column */
        $typeConverters = [];
        foreach ($rel->getColumns() as $i => $column) {
            $colNames[] = ($column->getName() ?? 'column' . ($i + 1));
            $type = $column->getType();
            if ($type !== null) {
                $typeConverters[] = $type;
            } else {
                $colIdent = ($column->getName() !== null ?
                    'column ' . $column->getName() :
                    StringUtils::englishOrd($i+1) . ' column'
                );
                throw new \InvalidArgumentException("Invalid relation for serialization - $colIdent has unknown type");
            }
        }

        if (!$colNames) { // a relation without any columns may not be represented using the VALUES construct
            $result = 'SELECT';
            if ($rel->count() == 0) {
                $result .= ' WHERE FALSE';
            } elseif ($rel->count() > 1) { // even multiple rows are possible
                $result .= str_repeat(' UNION ALL SELECT', $rel->count() - 1);
            }
            return $result;
        }

        if ($rel->count() == 0) { // a relation without any rows may not be represented using the VALUES construct
            $identType = $this->getIdentifierType();
            $result = 'SELECT';
            foreach ($colNames as $i => $colName) {
                $type = $typeConverters[$i];
                $result .= ($i == 0 ? ' ' : ', ') . 'NULL';
                if ($type instanceof INamedType) {
                    $result .= "::{$type->getSchemaName()}.{$type->getName()}";
                }
                $result .= ' AS ' . $identType->serializeValue($colName);
            }
            $result .= ' WHERE FALSE';
            return $result;
        }

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
                $type = $typeConverters[$colIdx];
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
        foreach ($colNames as $i => $name) {
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

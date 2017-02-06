<?php
namespace Ivory\Type\Ivory;

use Ivory\Query\IRelationRecipe;

/**
 * Internal Ivory converter serializing relation recipes into SQL value.
 *
 * Used in SQL patterns to handle `%rel` placeholders. These will typically be used in subselects or in
 * {@link https://www.postgresql.org/docs/current/static/queries-with.html `WITH` queries (common table expressions)}.
 *
 * Note that an {@link \InvalidArgumentException} is thrown when serializing `null` as that clearly signifies an error.
 */
class RelationRecipeType extends VolatilePatternTypeBase
{
    public function serializeValue($val)
    {
        if (!$val instanceof IRelationRecipe) {
            throw new \InvalidArgumentException('Expecting an ' . IRelationRecipe::class . ' object');
        }

        $typeDictionary = $this->getConnection()->getTypeDictionary();
        return $val->toSql($typeDictionary);
    }
}

<?php
namespace Ivory\Type\Ivory;

use Ivory\Query\ICommandRecipe;

/**
 * Internal Ivory converter serializing command recipes into SQL value.
 *
 * Used in SQL patterns to handle `%cmd` placeholders. These will typically be used combined with the `RETURNING`
 * clause, either as single `INSERT` or `UPDATE` commands for which the caller wants to fetch the written rows, or in
 * {@link https://www.postgresql.org/docs/current/static/queries-with.html `WITH` queries (common table expressions)}.
 *
 * Note that an {@link \InvalidArgumentException} is thrown when serializing `null` as that clearly signifies an error.
 */
class CommandRecipeType extends VolatilePatternTypeBase
{
    public function serializeValue($val): string
    {
        if (!$val instanceof ICommandRecipe) {
            throw new \InvalidArgumentException('Expecting an ' . ICommandRecipe::class . ' object');
        }

        $typeDictionary = $this->getConnection()->getTypeDictionary();
        return $val->toSql($typeDictionary);
    }
}

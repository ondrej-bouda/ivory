<?php
namespace Ivory\Type\Ivory;

use Ivory\Query\ICommandRecipe;

/**
 * Internal Ivory value serializer for serializing command recipes into SQL statements.
 *
 * Used in SQL patterns to handle `%cmd` placeholders. These will typically be used combined with the `RETURNING`
 * clause, either as single `INSERT` or `UPDATE` commands for which the caller wants to fetch the written rows, or in
 * {@link https://www.postgresql.org/docs/current/static/queries-with.html `WITH` queries (common table expressions)}.
 *
 * Note that an {@link \InvalidArgumentException} is thrown when serializing `null` as that clearly signifies an error.
 */
class CommandSerializer extends ConnectionDependentValueSerializer
{
    public function serializeValue($val): string
    {
        if ($val instanceof ICommandRecipe) {
            $typeDictionary = $this->getConnection()->getTypeDictionary();
            return $val->toSql($typeDictionary);
        } else {
            throw new \InvalidArgumentException('Expecting an ' . ICommandRecipe::class . ' object');
        }
    }
}
<?php
declare(strict_types=1);
namespace Ivory\Type\Ivory;

use Ivory\Query\ICommand;

/**
 * Internal Ivory value serializer for serializing commands into SQL statements.
 *
 * Used in SQL patterns to handle `%cmd` placeholders. These will typically be used combined with the `RETURNING`
 * clause, either as single `INSERT` or `UPDATE` commands for which the caller wants to fetch the written rows, or in
 * {@link https://www.postgresql.org/docs/current/static/queries-with.html `WITH` queries (common table expressions)}.
 *
 * Note that an {@link \InvalidArgumentException} is thrown when serializing `null` as that clearly signifies an error.
 */
class CommandSerializer extends ConnectionDependentValueSerializer
{
    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val instanceof ICommand) {
            $typeDictionary = $this->getConnection()->getTypeDictionary();
            return $val->toSql($typeDictionary);
        } else {
            throw new \InvalidArgumentException('Expecting an ' . ICommand::class . ' object');
        }
    }
}

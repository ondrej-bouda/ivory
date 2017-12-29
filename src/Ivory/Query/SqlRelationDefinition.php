<?php
declare(strict_types=1);
namespace Ivory\Query;

/**
 * Relation definition given by an SQL query.
 *
 * {@inheritdoc}
 */
class SqlRelationDefinition extends RelationDefinition implements ISqlPatternStatement
{
    use SqlPatternDefinitionMacros;
}

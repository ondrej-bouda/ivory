<?php
namespace Ivory\Query;

/**
 * Relation definition given by an SQL query.
 *
 * {@inheritdoc}
 */
class SqlRelationDefinition extends RelationDefinition implements ISqlPatternDefinition
{
    use SqlPatternDefinitionMacros;
}

<?php
namespace Ivory\Query;

/**
 * SQL recipe for a relation.
 *
 * {@inheritdoc}
 */
class SqlRelationRecipe extends RelationRecipe implements ISqlPatternRecipe
{
    use SqlPatternRecipeMacros;
}

<?php
namespace Ivory\Query;

/**
 * SQL recipe for a command.
 *
 * {@inheritdoc}
 */
class SqlCommandRecipe extends CommandRecipe implements ISqlPatternRecipe
{
    use SqlPatternRecipeMacros;
}

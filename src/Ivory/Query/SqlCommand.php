<?php
namespace Ivory\Query;

/**
 * Command defined by an SQL statement.
 *
 * {@inheritdoc}
 */
class SqlCommand extends Command implements ISqlPatternDefinition
{
    use SqlPatternDefinitionMacros;
}

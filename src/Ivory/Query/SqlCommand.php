<?php
declare(strict_types=1);

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

<?php
declare(strict_types=1);
namespace Ivory\Query;

/**
 * Command defined by an SQL statement.
 */
class SqlCommand extends Command implements ISqlPatternStatement
{
    use SqlPatternDefinitionMacros;
}

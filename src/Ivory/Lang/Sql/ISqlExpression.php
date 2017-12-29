<?php
declare(strict_types=1);
namespace Ivory\Lang\Sql;

interface ISqlExpression
{
    /**
     * @return string the SQL expression string
     */
    function getSql(): string;
}

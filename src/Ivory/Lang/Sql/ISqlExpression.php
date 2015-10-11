<?php
namespace Ivory\Lang\Sql;

interface ISqlExpression
{
    /**
     * @return string the SQL expression string
     */
    function getSql();
}

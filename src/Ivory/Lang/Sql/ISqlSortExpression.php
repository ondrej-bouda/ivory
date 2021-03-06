<?php
declare(strict_types=1);
namespace Ivory\Lang\Sql;

/**
 * An expression according to which a relation may be sorted, including the sorting direction.
 */
interface ISqlSortExpression
{
    const ASC = 'ASC';
    const DESC = 'DESC';

    /**
     * @return ISqlExpression the expression according to which to sort
     */
    function getExpression(): ISqlExpression;

    /**
     * @return string the sorting direction; either {@link ISqlSortExpression::ASC} or {@link ISqlSortExpression::DESC}
     */
    function getDirection(): string;
}

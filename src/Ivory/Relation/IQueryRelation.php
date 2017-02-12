<?php
namespace Ivory\Relation;

use Ivory\Lang\Sql\ISqlPredicate;
use Ivory\Relation\Alg\ITupleFilter;

/**
 * Relation defined by an SQL query.
 */
interface IQueryRelation extends IRelation
{
    /**
     * @return string the SQL query to be executed for this relation
     */
    function getSql();
}

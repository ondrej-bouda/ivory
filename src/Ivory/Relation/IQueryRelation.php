<?php
namespace Ivory\Relation;

/**
 * Relation defined by an SQL query.
 */
interface IQueryRelation extends IRelation
{
    /**
     * @return string the SQL query executed for this relation
     */
    function getQuery();
}

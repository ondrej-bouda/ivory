<?php
namespace Ivory\Result;

use Ivory\Relation\IRelation;

/**
 * Result of a successful query, returning a relation.
 */
interface IQueryResult extends IResult, IRelation
{

}

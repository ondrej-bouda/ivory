<?php
namespace Ivory\Query;

use Ivory\Type\ITypeDictionary;

/**
 * Specification for a relation.
 */
interface IRelationRecipe
{
    /**
     * @param ITypeDictionary $typeDictionary
     * @return string the SQL query to be executed, resulting in the desired relation
     */
    function getSql(ITypeDictionary $typeDictionary) : string;
}

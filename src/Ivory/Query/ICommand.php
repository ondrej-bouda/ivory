<?php
declare(strict_types=1);
namespace Ivory\Query;

use Ivory\Type\ITypeDictionary;

/**
 * Specification for a command.
 */
interface ICommand
{
    /**
     * @param ITypeDictionary $typeDictionary
     * @return string the SQL query to be executed as the desired command
     */
    function toSql(ITypeDictionary $typeDictionary): string;
}

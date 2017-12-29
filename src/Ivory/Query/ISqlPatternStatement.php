<?php
declare(strict_types=1);
namespace Ivory\Query;

use Ivory\Exception\InvalidStateException;
use Ivory\Exception\UndefinedTypeException;
use Ivory\Lang\SqlPattern\SqlPattern;
use Ivory\Type\ITypeDictionary;

/**
 * A definition given by a parametrized SQL pattern.
 *
 * @see SqlPattern for thorough details on SQL patterns.
 */
interface ISqlPatternStatement
{
    /**
     * Sets the value of a parameter in the SQL pattern.
     *
     * @param string|int $nameOrPosition name of the named parameter, or (zero-based) position of the positional
     *                                     parameter, respectively
     * @param mixed $value value of the parameter;
     *                     if the parameter is specified explicitly with its type, <tt>$value</tt> must correspond to
     *                       the type;
     *                     otherwise, the type of the parameter (and thus the conversion to be used) is inferred from
     *                       the type of <tt>$value</tt>
     * @return $this
     * @throws \InvalidArgumentException when the SQL pattern has no parameter of a given name or position
     */
    function setParam($nameOrPosition, $value);

    /**
     * Sets values of several parameters in the SQL pattern.
     *
     * @param iterable $paramMap map: parameter name (or zero-based position) => parameter value
     * @return $this
     */
    function setParams(iterable $paramMap);

    function getSqlPattern(): SqlPattern;

    /**
     * @return array map: parameter name or zero-based position => parameter value
     */
    function getParams(): array;

    /**
     * Serializes this definition into an SQL string.
     *
     * @param ITypeDictionary $typeDictionary
     * @param array $namedParameterValues values of named parameters for just this serialization
     * @return string
     * @throws InvalidStateException when values for one or more named parameters has not been set
     * @throws UndefinedTypeException when some of the types used in the pattern are not defined
     */
    function toSql(ITypeDictionary $typeDictionary, array $namedParameterValues = []): string;
}

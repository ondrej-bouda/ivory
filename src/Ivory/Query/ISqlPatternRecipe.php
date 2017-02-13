<?php
namespace Ivory\Query;

use Ivory\Lang\SqlPattern\SqlPattern;

/**
 * Recipe defined by a parametrized SQL pattern.
 *
 * @see SqlPattern for thorough details on SQL patterns.
 */
interface ISqlPatternRecipe
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
     * @param array|\Traversable $paramMap map: parameter name (or zero-based position) => parameter value
     * @return $this
     */
    function setParams($paramMap); // PHP 7.1: declare $paramMap as iterable

    function getSqlPattern(): SqlPattern;

    /**
     * @return array map: parameter name or zero-based position => parameter value
     */
    function getParams(): array;
}

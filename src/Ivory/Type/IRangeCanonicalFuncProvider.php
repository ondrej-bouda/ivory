<?php
namespace Ivory\Type;

/**
 * Responsible for providing implementations of canonical functions used by range types.
 */
interface IRangeCanonicalFuncProvider
{
    /**
     * Provides an implementation of a given canonical function working on a totally ordered type.
     *
     * @param string $schemaName name of schema the canonical function is defined in
     * @param string $funcName name of the canonical function; just the name, without parentheses or arguments
     * @param ITotallyOrderedType $subtype subtype for the canonical function to work on
     * @return IRangeCanonicalFunc|null the implementation of the range canonical function, or
     *                                  <tt>null</tt> if this provider has no matching implementation
     */
    function provideCanonicalFunc(string $schemaName, string $funcName, ITotallyOrderedType $subtype);
}

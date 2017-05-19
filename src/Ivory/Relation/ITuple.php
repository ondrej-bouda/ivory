<?php
namespace Ivory\Relation;

use Ivory\Exception\UndefinedColumnException;
use Ivory\Exception\AmbiguousException;
use Ivory\Relation\Alg\ITupleEvaluator;
use Ivory\Utils\IEqualable;

/**
 * Represents one relation row.
 *
 * A tuple is a map of offsets or names of the originating columns to the corresponding values. There are three ways of
 * accessing an attribute value:
 * - via the column zero-based offset using **`ArrayAccess`** (e.g., `$tuple[0]` is the value of the first column); or
 * - via the column name using the **dynamic property access** (e.g., `$tuple->foo` exposes the value of the column
 *   named "foo"; for columns which do not form a correct PHP identifier, use the PHP syntax `$tuple->{'some col'}`); or
 * - via the **{@link value()} method**, which accepts both column offsets and names, and also a custom evaluator
 *   function; note that the universality of the {@link value()} method comes at the cost of a slight performance
 *   penalty compared to the first two access methods.
 *
 * Note there might be several columns of the same name defined on the originating relation. To prevent ambiguity, the
 * tuple throws an `AmbiguousException` when accessing a column by an ambiguous name.
 *
 * There might also be a column with no name. The value of an anonymous column is only accessible by the column offset.
 *
 * @internal Ivory design note: ITuple is intentionally not iterable as there seems to be no real value with it. It
 * might be added later with a use case in hand.
 */
interface ITuple extends \ArrayAccess, IEqualable
{
    /**
     * Converts the tuple to a list of values from the individual columns.
     *
     * @see toMap()
     *
     * @return array list of values of this tuple
     */
    function toList(): array;

    /**
     * Converts the tuple to an associative array of column names to the corresponding values held by this tuple.
     *
     * Note there might be several columns of the same name defined on the originating relation. It this is the case,
     * `toMap()` throws a `AmbiguousException` as it would be ambiguous which value to return under the duplicated key.
     *
     * Also note some columns in the originating relation might not have name. Values of such anonymous columns are
     * omitted from the result of `toMap()` as there would be no key to give them.
     *
     * @see toList()
     *
     * @internal Ivory design note: The original specification for the case of multiple same-named columns was to return
     * the value of the first column of the group. The current specification goes with the more restrictive API,
     * throwing exception on ambiguous name, just as PostgreSQL does. If that proves too strict, the API might be
     * relaxed in later versions.
     *
     * @return array map: column name => value
     * @throws AmbiguousException
     */
    function toMap(): array;

    /**
     * @param int|string|ITupleEvaluator|\Closure $colOffsetOrNameOrEvaluator
     *                                  Specification of column from which to get the value. See
     *                                    {@link IRelation::col()} for more details on the column specification.
     * @return mixed
     * @throws UndefinedColumnException if no column matches the specification
     * @throws AmbiguousException if requesting the value by a column name which is used by multiple columns
     */
    function value($colOffsetOrNameOrEvaluator);

    /**
     * @param string $name column name
     * @return mixed value for the given column, or <tt>null</tt> if no such column is defined on the tuple, or multiple
     *                 columns of the same name are defined
     * @throws UndefinedColumnException if there is no column of the given name
     * @throws AmbiguousException if there are multiple columns of the given name
     */
    function __get($name);

    /**
     * @param string $name column name
     * @return bool whether the column is defined above the tuple
     */
    function __isset($name);
}

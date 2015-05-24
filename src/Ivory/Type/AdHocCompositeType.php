<?php
namespace Ivory\Type;

/**
 * Represents an unnamed composite type.
 *
 * Unnamed composite types are typically returned from queries which construct the composition on-the-fly, such as any
 * <tt>SELECT</tt> queries - e.g., <tt>SELECT 1 AS one</tt> returns a value of ad-hoc composite type ("one": numeric).
 */
class AdHocCompositeType extends CompositeTypeBase
{

}

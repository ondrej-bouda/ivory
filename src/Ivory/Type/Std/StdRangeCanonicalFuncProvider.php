<?php
namespace Ivory\Type\Std;

use Ivory\Exception\UnsupportedException;
use Ivory\Type\IDiscreteType;
use Ivory\Type\IRangeCanonicalFuncProvider;
use Ivory\Type\ITotallyOrderedType;

/**
 * Provides implementations of PostgreSQL standard range canonical functions.
 *
 * By convention, all PostgreSQL standard canonical functions use a canonical form that includes the lower bound and
 * excludes the upper bound; that is, `[)`.
 *
 * @see http://www.postgresql.org/docs/9.4/static/rangetypes.html#RANGETYPES-DISCRETE
 */
class StdRangeCanonicalFuncProvider implements IRangeCanonicalFuncProvider
{
    public function provideCanonicalFunc($schemaName, $funcName, ITotallyOrderedType $subtype)
    {
        if ($schemaName != 'pg_catalog') {
            return null;
        }

        switch ($funcName) {
            case 'daterange_canonical':
            case "int4range_canonical":
            case "int8range_canonical":
                if (!$subtype instanceof IDiscreteType) {
                    $msg = 'The ' . ConventionalRangeCanonicalFunc::class . ' only works on ' . IDiscreteType::class;
                    throw new UnsupportedException($msg);
                }
                return new ConventionalRangeCanonicalFunc($subtype);

            default:
                return null;
        }
    }
}

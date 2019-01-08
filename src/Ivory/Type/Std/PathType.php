<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Value\Path;

/**
 * Path on a plane.
 *
 * Represented as a {@link \Ivory\Value\Path} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-geometric.html
 */
class PathType extends CompoundGeometricType
{
    public function parseValue(string $extRepr)
    {
        $re = '~^ \s*
                (?:(\()|(\[))? \s*                  # optional opening parenthesis or bracket
                (
                  (\()?[^,]+,[^,]+(?(4)\)) \s*      # the first endpoint; optionally parenthesized
                  (?:                               # the rest of the endpoints
                    , \s*
                    (?(4)\()[^,]+,[^,]+(?(4)\)) \s* # parenthesized iff the first endpoint is parenthesized
                  )*
                )
                (?(1)\)|(?(2)\])) \s*               # pairing closing parenthesis or bracket
                $~x';

        if (preg_match($re, $extRepr, $m)) {
            $isOpen = isset($m[2]);
            $points = [];
            $pointListStr = $m[3];
            $fragments = explode(',', $pointListStr);
            try {
                for ($i = 0; $i < count($fragments); $i += 2) {
                    $points[] = $this->pointType->parseValue($fragments[$i] . ',' . $fragments[$i + 1]);
                }
                return Path::fromPoints($points, ($isOpen ? Path::OPEN : Path::CLOSED));
            } catch (\InvalidArgumentException $e) {
                throw $this->invalidValueException($extRepr, $e);
            }
        } else {
            throw $this->invalidValueException($extRepr);
        }
    }

    public function serializeValue($val, bool $forceType = false): string
    {
        if ($val === null) {
            return $this->typeCastExpr($forceType, 'NULL');
        }

        if (!$val instanceof Path) {
            try {
                $val = Path::fromPoints($val);
            } catch (\InvalidArgumentException $e) {
                throw $this->invalidValueException($val, $e);
            }
        }

        $points = [];
        foreach ($val->getPoints() as $point) {
            $points[] = $this->pointType->serializeValue($point);
        }

        $expr =
            ($val->isOpen() ? '[' : '(') .
            implode(',', $points) .
            ($val->isOpen() ? ']' : ')');

        return $this->indicateType($forceType, $expr);
    }
}

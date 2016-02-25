<?php
namespace Ivory\Type;

use Ivory\Exception\IncomparableException;
use Ivory\NamedDbObject;
use Ivory\Value\Range;

/**
 * Converter for ranges.
 *
 * @see http://www.postgresql.org/docs/9.4/static/rangetypes.html
 */
class RangeType implements INamedType, ITotallyOrderedType
{
    use NamedDbObject;

    private $subtype;
    private $canonicalFunc;

    public function __construct($schemaName, $name, ITotallyOrderedType $subtype, IRangeCanonicalFunc $canonicalFunc = null)
    {
        $this->setName($schemaName, $name);
        $this->subtype = $subtype;
        $this->canonicalFunc = $canonicalFunc;
    }

    public function getSubtype()
    {
        return $this->subtype;
    }

    public function getCanonicalFunc()
    {
        return $this->canonicalFunc;
    }

    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }

        if (preg_match('~^\s*empty\s*$~i', $str)) {
            return Range::createEmpty($this->subtype);
        }

        $regex = '~
                   ^\s*
                   (?P<open> [[(] )
                   (?P<lower> "(?:[^"\\\\]|""|\\\\.)*"  # either a double-quoted string (backslashes used for escaping,
                                                        # or double quotes double for a single double-quote character),
                              |                         # or an unquoted string of characters which do not confuse the
                              (?:[^"\\\\()[\],]|\\\\.)* # parser or are backslash-escaped
                   )
                   ,
                   (?P<upper> (?P>lower) )              # the upper-bound follows the same rules as the lower-bound
                   (?P<close> [])] )
                   \s*$
                  ~x';
        if (!preg_match($regex, $str, $m)) {
            throw new \InvalidArgumentException("Invalid value for range {$this->getSchemaName()}.{$this->getName()}");
        }

        $lowerInc = ($m['open'] == '[');
        $upperInc = ($m['close'] == ']');
        $lower = $this->parseBoundStr($m['lower']);
        $upper = $this->parseBoundStr($m['upper']);

        return Range::createFromBounds($this->subtype, $this->canonicalFunc, $lower, $upper, $lowerInc, $upperInc);
    }

    private function parseBoundStr($str)
    {
        if (strlen($str) == 0) {
            return null;
        }

        if ($str[0] == '"') {
            $str = substr($str, 1, -1);
        }
        $unescaped = preg_replace(['~\\\\(.)~', '~""~'], ['$1', '"'], $str);
        return $this->subtype->parseValue($unescaped);
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof Range) {
            if (is_array($val) && isset($val[0], $val[1]) && count($val) == 2) {
                $val = Range::createFromBounds($this->subtype, $this->canonicalFunc, $val[0], $val[1], true, true);
            }
            else {
                $message = "Value '$val' is not valid for type {$this->getSchemaName()}.{$this->getName()}";
                throw new \InvalidArgumentException($message);
            }
        }

        if ($val->isEmpty()) {
            return sprintf("'empty'::%s.%s", $this->getSchemaName(), $this->getName());
        }

        $boundsSpec = $val->getBoundsSpec();
        return sprintf(
            "%s.%s(%s,%s%s)",
            $this->getSchemaName(), $this->getName(),
            $this->subtype->serializeValue($val->getLower()),
            $this->subtype->serializeValue($val->getUpper()),
            ($boundsSpec == '[)' || ($boundsSpec == '()' && $val->getLower() === null) ? '' : ",'$boundsSpec'")
        );
    }

    public function compareValues($a, $b)
    {
        if ($a === null || $b === null) {
            return null;
        }

        if (!$a instanceof Range) {
            throw new IncomparableException('$a is not a ' . Range::class);
        }
        if (!$b instanceof Range) {
            throw new IncomparableException('$b is not a ' . Range::class);
        }
        if ($a->getSubtype() !== $this->subtype) {
            throw new IncomparableException('$a of an incompatible subtype');
        }
        if ($b->getSubtype() !== $this->subtype) {
            throw new IncomparableException('$b of an incompatible subtype');
        }

        // empty ranges are sorted before all else in PostgreSQL
        if ($a->isEmpty() && $b->isEmpty()) {
            return 0;
        }
        elseif ($a->isEmpty()) {
            return -1;
        }
        elseif ($b->isEmpty()) {
            return 1;
        }
        else {
            $cmp = $this->compareBounds(-1, $a->getLower(), $a->isLowerInc(), $b->getLower(), $b->isLowerInc());
            if ($cmp != 0) {
                return $cmp;
            }
            else {
                return $this->compareBounds(1, $a->getUpper(), $a->isUpperInc(), $b->getUpper(), $b->isUpperInc());
            }
        }
    }

    private function compareBounds($sgn, $aVal, $aIsInc, $bVal, $bIsInc)
    {
        if ($aVal === null && $bVal === null) {
            return 0;
        }
        elseif ($aVal === null) {
            return 1 * $sgn;
        }
        elseif ($bVal === null) {
            return -1 * $sgn;
        }

        $cmp = $this->subtype->compareValues($aVal, $bVal);
        if ($cmp != 0) {
            return $cmp;
        }

        // PHP 7: <=> could lead to a more compact form
        if ($aIsInc && $bIsInc) {
            return 0;
        }
        elseif ($aIsInc) {
            return 1 * $sgn;
        }
        elseif ($bIsInc) {
            return -1 * $sgn;
        }
        else {
            return 0;
        }
    }
}

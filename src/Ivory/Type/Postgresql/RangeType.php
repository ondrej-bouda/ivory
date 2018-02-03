<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\NamedDbObject;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Value\Range;

/**
 * Type object for ranges.
 *
 * @see http://www.postgresql.org/docs/9.4/static/rangetypes.html
 */
class RangeType implements ITotallyOrderedType
{
    use NamedDbObject;

    private $subtype;

    public function __construct(string $schemaName, string $name, ITotallyOrderedType $subtype)
    {
        $this->setName($schemaName, $name);
        $this->subtype = $subtype;
    }

    public function getSubtype(): ITotallyOrderedType
    {
        return $this->subtype;
    }

    public function parseValue(string $extRepr)
    {
        if (preg_match('~^\s*empty\s*$~i', $extRepr)) {
            return Range::empty();
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
        if (!preg_match($regex, $extRepr, $m)) {
            throw new \InvalidArgumentException("Invalid value for range {$this->getSchemaName()}.{$this->getName()}");
        }

        $lowerInc = ($m['open'] == '[');
        $upperInc = ($m['close'] == ']');
        $lower = $this->parseBoundStr($m['lower']);
        $upper = $this->parseBoundStr($m['upper']);

        return Range::fromBounds($lower, $upper, $lowerInc, $upperInc);
    }

    private function parseBoundStr(string $str)
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

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof Range) {
            if (is_array($val) && isset($val[0], $val[1]) && count($val) == 2) {
                $val = Range::fromBounds($val[0], $val[1], true, true);
            } else {
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
}

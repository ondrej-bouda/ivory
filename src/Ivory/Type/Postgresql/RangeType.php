<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\Lang\Sql\Types;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\TypeBase;
use Ivory\Value\Range;

/**
 * Type object for ranges.
 *
 * @see https://www.postgresql.org/docs/11/rangetypes.html
 */
class RangeType extends TypeBase implements ITotallyOrderedType
{
    private $subtype;

    public function __construct(string $schemaName, string $name, ITotallyOrderedType $subtype)
    {
        parent::__construct($schemaName, $name);
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

        $regex = /** @lang PhpRegExp */
            '~
              ^\s*
              (?P<open> [[(] )
              (?P<lower> "(?:[^"\\\\]|""|\\\\.)*"       # either a double-quoted string (backslashes used for escaping,
                                                        # or double quotes double for a single double-quote character),
                         |                              # or an unquoted string of characters which do not confuse the
                         (?:[^"\\\\()[\],]|\\\\.)*      # parser or are backslash-escaped
              )
              ,
              (?P<upper> (?P>lower) )                   # the upper-bound follows the same rules as the lower-bound
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

        return $this->createParsedRange($lower, $upper, $lowerInc, $upperInc);
    }

    protected function createParsedRange($lower, $upper, bool $lowerInc, bool $upperInc): Range
    {
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

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
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
            return $this->indicateType($strictType, "'empty'");
        }

        $boundsSpec = $val->getBoundsSpec();
        return sprintf(
            "%s.%s(%s,%s%s)",
            Types::serializeIdent($this->getSchemaName()),
            Types::serializeIdent($this->getName()),
            $this->subtype->serializeValue($val->getLower(), false),
            $this->subtype->serializeValue($val->getUpper(), false),
            ($boundsSpec == '[)' || ($boundsSpec == '()' && $val->getLower() === null) ? '' : ",'$boundsSpec'")
        );
        // FIXME: The fact that '[)' bounds are default is rather conventional, and might not hold for user-defined
        //        ranges.
    }
}

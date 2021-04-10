<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Lang\Sql\Types;
use Ivory\Type\TypeBase;
use Ivory\Value\TextSearchVector;

/**
 * Text-search vector.
 *
 * Represented as a {@link \Ivory\Value\TextSearchVector} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-textsearch.html#DATATYPE-TSVECTOR
 */
class TsVectorType extends TypeBase
{
    const DEFAULT_WEIGHT = 'D';

    public function parseValue(string $extRepr)
    {
        preg_match_all('~\'((?:[^\']+|\'\')+)\'(?::([\d\w,]+))?~', $extRepr, $matches, PREG_SET_ORDER);
        $lexemes = [];
        foreach ($matches as $m) {
            $lex = strtr($m[1], ["''" => "'"]);
            if (isset($m[2])) {
                $positions = [];
                foreach (explode(',', $m[2]) as $pos) {
                    $p = (int)$pos;
                    $w = ((string)$p !== $pos ? substr($pos, strlen((string)$p)) : self::DEFAULT_WEIGHT);
                    $positions[] = [$p, $w];
                }
            } else {
                $positions = null;
            }
            $lexemes[$lex] = $positions;
        }

        return TextSearchVector::fromMap($lexemes);
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        }

        if (!$val instanceof TextSearchVector) {
            $val = TextSearchVector::fromString($val);
        }

        $tokens = [];
        foreach ($val->getLexemes() as $lex => $positions) {
            $tok = preg_replace('~.*[\s\':].*~', "'\\0'", strtr($lex, ["'" => "''"]));
            if ($positions) {
                $tokPos = [];
                foreach ($positions as [$pos, $weight]) {
                    $tp = $pos;
                    if ($weight != self::DEFAULT_WEIGHT) {
                        $tp .= $weight;
                    }
                    $tokPos[] = $tp;
                }
                $tok .= ':' . implode(',', $tokPos);
            }
            $tokens[] = $tok;
        }

        return $this->indicateType($strictType, Types::serializeString(implode(' ', $tokens)));
    }
}

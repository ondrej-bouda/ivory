<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\TextSearchVector;

/**
 * Text-search vector.
 *
 * Represented as a {@link \Ivory\Value\TextSearchVector} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-textsearch.html#DATATYPE-TSVECTOR
 */
class TsVectorType extends BaseType
{
    const DEFAULT_WEIGHT = 'D';

    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }
        else {
            preg_match_all('~\'((?:[^\']+|\'\')+)\'(?::([\d\w,]+))?~', $str, $matches, PREG_SET_ORDER);
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
                }
                else {
                    $positions = null;
                }
                $lexemes[$lex] = $positions;
            }
            return TextSearchVector::fromMap($lexemes);
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!$val instanceof TextSearchVector) {
            $val = TextSearchVector::fromString($val);
        }

        $tokens = [];
        $quoted = 0;
        foreach ($val->getLexemes() as $lex => $positions) {
            $tok = preg_replace('~.*[\s\':].*~', "'\\0'", strtr($lex, ["'" => "''"]), -1, $cnt);
            $quoted += $cnt;
            if ($positions) {
                $tokPos = [];
                foreach ($positions as list($pos, $weight)) {
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

        $q = ($quoted ? '$$' : "'"); // a friendly quoting - using dollar quotes only if useful
        return $q . implode(' ', $tokens) . $q;
    }
}

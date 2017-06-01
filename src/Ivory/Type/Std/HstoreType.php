<?php
namespace Ivory\Type\Std;

/**
 * String hash table.
 *
 * Represented as PHP `array` with string keys and values.
 *
 * For serializing to PostgreSQL `hstore`, objects are also accepted, the attributes of which are iterated using regular
 * `foreach` and key-value pairs stored in the hash.
 *
 * @see https://www.postgresql.org/docs/9.6/static/hstore.html
 */
class HstoreType extends \Ivory\Type\BaseType
{
    public function parseValue(string $str)
    {
        $re = '~
                \s*
                (?: " ((?: [^"\\\\] | \\\\" | \\\\\\\\ )*) "
                    |
                    ([^\s,=>"]+)
                )
                \s* => \s*
                (?: "((?1))" | (NULL) | ((?2)) )
                \s*
               ~iux';
        $result = [];
        $offset = 0;
        while (preg_match($re, $str, $m, PREG_OFFSET_CAPTURE, $offset)) {
            if ($m[0][1] != $offset) {
                throw new \InvalidArgumentException('Invalid syntax of hstore value');
            }

            $k = ($m[2][0] ? : $this->unescapeAtom($m[1][0]));
            $v = (!empty($m[4][0]) ? null : (!empty($m[5][0]) ? $m[5][0] : $this->unescapeAtom($m[3][0])));
            $result[$k] = $v;

            $offset += strlen($m[0][0]);
            if ($offset == strlen($str)) {
                break;
            }
            if ($str[$offset] != ',') {
                throw new \InvalidArgumentException('Invalid syntax of hstore value');
            }

            $offset++;
        }
        if (strlen($str) > $offset) {
            $pregError = preg_last_error();
            switch ($pregError) {
                case PREG_NO_ERROR:
                    throw new \InvalidArgumentException('Invalid syntax of hstore value');
                case PREG_JIT_STACKLIMIT_ERROR:
                    return $this->parseLongValue($str);
                default:
                    throw new \RuntimeException("PCRE error $pregError when matching at offset $offset");
            }
        }

        return $result;
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!is_array($val) && !is_object($val)) {
            throw new \InvalidArgumentException('Invalid value to be serialized to "hstore".');
        }

        $res = '';
        $isFirst = true;
        foreach ($val as $k => $v) {
            if (!$isFirst) {
                $res .= ',';
            } else {
                $isFirst = false;
            }
            $res .= $this->quoteAtom($k) . '=>' . $this->quoteAtom($v);
        }
        return $res;
    }

    private function quoteAtom($atom): string
    {
        if ($atom === null) {
            return 'NULL';
        } else {
            return '"' . strtr($atom, ['"' => '\\"', '\\' => '\\\\']) . '"';
        }
    }

    private function unescapeAtom(string $escapedAtom): string
    {
        return strtr($escapedAtom, ['\\"' => '"', '\\\\' => '\\']);
    }

    private function parseLongValue($str)
    {
        if ($str === null) {
            return null;
        }
        if (trim($str) === '') {
            return [];
        }

        if (iconv_strlen($str, 'UTF-8') === false) {
            throw new \RuntimeException('Invalid encoding of hstore value - not a valid UTF-8 string.');
        }

        // Correctness: Normally, multibyte-string functions shall be used. However, none of the control characters
        //              conflict with higher bytes of a single character in UTF-8.
        $len = strlen($str);
        $result = []; // the resulting map
        $state = 0;
        $key = null; // the parsed key of the current key-value pair
        $val = null; // the parsed value of the current key-value pair
        $ctlChars = ',=>';
        for ($i = 0; $i < $len; $i++) {
            $ch = $str[$i];
            switch ($state) {
                case 0:
                    if ($ch == '"') {
                        $key = '';
                        $state = 1;
                    } elseif (ctype_space($ch)) {
                        continue 2;
                    } elseif (strpos($ctlChars, $ch) === false) {
                        $key = $ch;
                        $state = 3;
                    } else {
                        break 2;
                    }
                    break;

                case 1:
                    if ($ch == '\\') {
                        $state = 2;
                    } elseif ($ch == '"') {
                        $state = 4;
                    } else {
                        $key .= $ch;
                    }
                    break;

                case 2:
                    $key .= $ch;
                    $state = 1;
                    break;

                case 3:
                    if ($ch == '=') {
                        $state = 5;
                    } elseif (ctype_space($ch)) {
                        $state = 4;
                    } elseif (strpos($ctlChars, $ch) === false) {
                        $key .= $ch;
                    } else {
                        break 2;
                    }
                    break;

                case 4:
                    if ($ch == '=') {
                        $state = 5;
                    } elseif (ctype_space($ch)) {
                        continue 2;
                    } else {
                        break 2;
                    }
                    break;

                case 5:
                    if ($ch == '>') {
                        $state = 6;
                    } else {
                        break 2;
                    }
                    break;

                case 6:
                    if (ctype_space($ch)) {
                        continue 2;
                    } elseif ($ch == '"') {
                        $val = '';
                        $state = 7;
                    } elseif (strpos($ctlChars, $ch) === false) {
                        $val = $ch;
                        $state = 9;
                    } else {
                        break 2;
                    }
                    break;

                case 7:
                    if ($ch == '"') {
                        $state = 10;
                    } elseif ($ch == '\\') {
                        $state = 8;
                    } else {
                        $val .= $ch;
                    }
                    break;

                case 8:
                    $val .= $ch;
                    $state = 7;
                    break;

                case 9:
                    if ($ch == ',') {
                        $result[$key] = (strcasecmp($val, 'NULL') == 0 ? null : $val);
                        $state = 0;
                    } elseif (ctype_space($ch)) {
                        $state = 10;
                    } elseif (strpos($ctlChars, $ch) === false) {
                        $val .= $ch;
                    } else {
                        $state = -1;
                        break 2;
                    }
                    break;

                case 10:
                    if ($ch == ',') {
                        $result[$key] = $val;
                        $state = 0;
                    } elseif (ctype_space($ch)) {
                        continue 2;
                    } else {
                        $state = -1;
                        break 2;
                    }
            }
        }

        if ($key !== null) {
            $v = ($state == 9 && strcasecmp($val, 'NULL') == 0 ? null : $val);
            $result[$key] = $v;
        }

        if ($state != 9 && $state != 10) {
            throw new \InvalidArgumentException("Invalid syntax of hstore value - unexpected character at offset $i (parser state $state)");
        }

        return $result;
    }
}

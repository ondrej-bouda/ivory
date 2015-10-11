<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;

/**
 * The UUID data type.
 *
 * Represented as the PHP `string` type.
 *
 * As defined by PostgreSQL, valid input is a sequence of 32 upper- or lowercase hexadecimal digits, where each group
 * four digits may be separated by a hyphen from the rest of the string, and the whole string may be surrounded by
 * braces.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-uuid.html
 */
class UuidType extends BaseType
{
    public function parseValue($str)
    {
        if ($str === null) {
            return null;
        }
        else {
            return $str;
        }
    }

    public function serializeValue($val)
    {
        if ($val === null) {
            return 'NULL';
        }

        if (!preg_match('~^(\{)?(?:[[:xdigit:]]{4}-?){7}[[:xdigit:]]{4}(?(1)\})$~i', $val)) {
            $this->throwInvalidValue($val);
        }
        return "'" . strtr($val, ["'" => "''"]) . "'";
    }
}

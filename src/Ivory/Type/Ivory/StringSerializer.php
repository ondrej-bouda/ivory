<?php
declare(strict_types=1);
namespace Ivory\Type\Ivory;

use Ivory\Type\IValueSerializer;

class StringSerializer implements IValueSerializer
{
    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        } else {
            return "'" . strtr((string)$val, ["'" => "''"]) . "'";
        }
    }
}

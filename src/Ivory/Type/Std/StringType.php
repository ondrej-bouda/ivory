<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\NamedDbObject;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\Ivory\StringSerializer;

/**
 * Character string.
 *
 * Represented as the PHP `string` type.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-character.html
 */
class StringType extends StringSerializer implements ITotallyOrderedType
{
    use NamedDbObject;

    public function __construct(string $schemaName, string $name)
    {
        $this->setName($schemaName, $name);
    }

    public function parseValue(string $extRepr)
    {
        return $extRepr;
    }
}

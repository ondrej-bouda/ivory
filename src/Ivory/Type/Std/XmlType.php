<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;
use Ivory\Value\XmlContent;
use Ivory\Value\XmlDocument;

/**
 * XML documents and content.
 *
 * Represented as an {@link \Ivory\Value\XmlContent} or {@link \Ivory\Value\XmlDocument} object.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-xml.html
 * @see http://www.postgresql.org/docs/9.4/static/functions-xml.html
 * @todo implement ITotallyOrderedType for this type to be applicable as a range subtype
 */
class XmlType extends BaseType
{
    public function parseValue(string $str)
    {
        return XmlContent::fromValue($str);
    }

    public function serializeValue($val): string
    {
        if ($val === null) {
            return 'NULL';
        }

        try {
            $xml = XmlContent::fromValue($val);
            return sprintf("XMLPARSE(%s '%s')",
                ($xml instanceof XmlDocument ? 'DOCUMENT' : 'CONTENT'),
                strtr($xml->toString(), ["'" => "''"])
            );
        } catch (\InvalidArgumentException $e) {
            throw $this->invalidValueException($val);
        }
    }
}

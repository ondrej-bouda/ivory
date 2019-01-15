<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Lang\Sql\Types;
use Ivory\Type\TypeBase;
use Ivory\Value\XmlContent;
use Ivory\Value\XmlDocument;

/**
 * XML documents and content.
 *
 * Represented as an {@link \Ivory\Value\XmlContent} or {@link \Ivory\Value\XmlDocument} object.
 *
 * @see https://www.postgresql.org/docs/11/datatype-xml.html
 * @see https://www.postgresql.org/docs/11/functions-xml.html
 */
class XmlType extends TypeBase
{
    public function parseValue(string $extRepr)
    {
        return XmlContent::fromValue($extRepr);
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return $this->typeCastExpr($strictType, 'NULL');
        }

        try {
            $xml = XmlContent::fromValue($val);
            return sprintf("XMLPARSE(%s %s)",
                ($xml instanceof XmlDocument ? 'DOCUMENT' : 'CONTENT'),
                Types::serializeString($xml->toString())
            );
        } catch (\InvalidArgumentException $e) {
            throw $this->invalidValueException($val);
        }
    }
}

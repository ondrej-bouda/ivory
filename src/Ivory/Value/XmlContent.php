<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Encapsulation of some XML content, not necessarily an XML document.
 *
 * The objects are immutable.
 */
class XmlContent
{
    private $xmlStr;

    /**
     * Creates a new XML content value. If the value forms a document, it creates an {@link XmlDocument} object.
     *
     * If an object of an unrecognized class is given, its string serialization is used as the XML string.
     *
     * @param string|XmlContent|\DOMDocument|\DOMNode|\DOMNodeList|\SimpleXMLElement|object $value
     * @return XmlContent
     */
    public static function fromValue($value): XmlContent
    {
        if (is_string($value)) {
            $xmlStr = $value;
            $isDoc = self::isXmlDocument($value);
        } elseif ($value instanceof XmlContent) {
            return $value;
        } elseif ($value instanceof \DOMDocument) {
            $xmlStr = $value->saveXML();
            if ($xmlStr === false) {
                throw new \InvalidArgumentException('value');
            }
            $isDoc = true;
        } elseif ($value instanceof \DOMNode) {
            $d = $value->ownerDocument->saveXML($value);
            if ($d === false) {
                throw new \InvalidArgumentException('value');
            }
            $xmlStr = self::getXMLDeclaration($value->ownerDocument) . $d;
            $isDoc = true;
        } elseif ($value instanceof \DOMNodeList) {
            $xmlStr = ($value->length > 0 ? self::getXMLDeclaration($value->item(0)->ownerDocument) : '');
            foreach ($value as $i => $node) {
                $n = $node->ownerDocument->saveXML($node);
                if ($n === false) {
                    throw new \InvalidArgumentException("value, node $i");
                }
                $xmlStr .= $n;
            }
            $isDoc = ($value->length == 1);
        } elseif ($value instanceof \SimpleXMLElement) {
            $xmlStr = $value->saveXML();
            if ($xmlStr === false) {
                throw new \InvalidArgumentException('value');
            }
            $isDoc = true;
        } elseif (is_object($value)) {
            $xmlStr = (string)$value;
            $isDoc = self::isXmlDocument($xmlStr);
        } else {
            throw new \InvalidArgumentException('value');
        }

        if ($isDoc) {
            return new XmlDocument($xmlStr);
        } else {
            return new XmlContent($xmlStr);
        }
    }

    private static function isXmlDocument(string $xmlStr): bool
    {
        $reader = new \XMLReader();
        /** @noinspection PhpStaticAsDynamicMethodCallInspection it is also a non-static method */
        $reader->XML($xmlStr);

        if (!@$reader->read()) {
            return false;
        }

        do {
            $read = @$reader->read();
        } while ($read);

        return ($reader->depth == 0);
    }

    private static function getXMLDeclaration(\DOMDocument $doc): string
    {
        $out = '<?xml version="' . $doc->xmlVersion . '"';
        if (strlen($doc->xmlEncoding) > 0) {
            $out .= ' encoding="' . $doc->xmlEncoding . '"';
        }
        if ($doc->xmlStandalone) {
            $out .= ' standalone="yes"';
        }
        $out .= '?>';

        return $out;
    }

    /**
     * @param string $xmlStr
     */
    private function __construct(string $xmlStr)
    {
        $this->xmlStr = $xmlStr;
    }

    /**
     * @return string the XML value as a string
     */
    public function toString(): string
    {
        return $this->xmlStr;
    }
}

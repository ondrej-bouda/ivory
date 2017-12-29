<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Encapsulation of an XML document.
 *
 * The objects are immutable.
 */
class XmlDocument extends XmlContent
{
    public function toDOMDocument(): \DOMDocument
    {
        $impl = new \DOMImplementation();
        $doc = $impl->createDocument();
        $doc->loadXML($this->toString());
        return $doc;
    }

    public function toSimpleXMLElement(): \SimpleXMLElement
    {
        return new \SimpleXMLElement($this->toString());
    }
}

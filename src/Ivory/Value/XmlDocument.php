<?php
namespace Ivory\Value;

/**
 * Encapsulation of an XML document.
 *
 * The objects are immutable.
 */
class XmlDocument extends XmlContent
{
    public function toDOMDocument()
    {
        $impl = new \DOMImplementation();
        $doc = $impl->createDocument();
        $doc->loadXML($this->toString());
        return $doc;
    }

    public function toSimpleXMLElement()
    {
        return new \SimpleXMLElement($this->toString());
    }
}

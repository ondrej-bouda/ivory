<?php
namespace Ivory\Type\Std;

/**
 * XML documents and content.
 *
 * Represented as plain PHP strings. The serializer accepts multiple representations, though, such as
 * {@link \DOMDocument}, {@link \DOMNodeList}, and others.
 *
 * Note there are multiple choices for representing XML documents and content in PHP when read from the DBMS, e.g., as
 * {@link \DOMDocument} and {@link \DOMDocumentFragment} objects, respectively, to name one. There seem to be pretty
 * much problems with these, however. Hence, the representation is plain string, left up to the caller to pick their
 * favorite XML representation (or simply using other XML type converter than this class).
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-xml.html
 * @see http://www.postgresql.org/docs/9.4/static/functions-xml.html
 * @todo instead of returning string, return a value object with conversion methods from/to XML types currently accepted by serializeValue(); either use one class with isXmlDocument() method, or use two classes - XmlContent and XmlDocument extending XmlContent
 */
class XmlType extends \Ivory\Type\BaseType
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

		if ($val instanceof \DOMDocument) {
			$str = $val->saveXML();
			if ($str === false) {
				$this->throwInvalidValue($val);
			}
			$isDocument = true;
		}
		elseif ($val instanceof \DOMNode) {
			$d = $val->ownerDocument->saveXML($val);
			if ($d === false) {
				$this->throwInvalidValue($val);
			}
			$str = self::saveXMLDeclaration($val->ownerDocument) . $d;
			$isDocument = true;
		}
		elseif ($val instanceof \DOMNodeList) {
			$str = ($val->length > 0 ? self::saveXMLDeclaration($val->item(0)->ownerDocument) : '');
			foreach ($val as $i => $node) {
				$n = $node->ownerDocument->saveXML($node);
				if ($n === false) {
					$this->throwInvalidValue("(node $i)");
				}
				$str .= $n;
			}
			$isDocument = false;
		}
		elseif ($val instanceof \SimpleXMLElement) {
			$str = $val->saveXML();
			if ($str === false) {
				$this->throwInvalidValue($val);
			}
			$isDocument = true;
		}
		elseif (is_string($val) || is_object($val)) {
			$str = (string)$val;
			$isDocument = self::isXmlDocument($str);
		}
		else {
			$this->throwInvalidValue($val);
		}

		return sprintf("XMLPARSE(%s '%s')",
			($isDocument ? 'DOCUMENT' : 'CONTENT'),
			strtr($str, ["'" => "''"])
		);
	}

	private static function saveXMLDeclaration(\DOMDocument $doc)
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

	private static function isXmlDocument($xml)
	{
		$reader = new \XMLReader();
		$reader->xml($xml);
		if (!@$reader->read()) {
			return false;
		}
		while (@$reader->read()) {}
		return ($reader->depth == 0);
	}
}

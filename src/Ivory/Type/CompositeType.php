<?php
namespace Ivory\Type;

use Ivory\Value\Composite;

/**
 * A composite type is basically a tuple of values.
 *
 * It is optional for a `CompositeType` to have its attributes defined. It is has, it is called "typed", otherwise, we
 * consider it as "untyped".
 * - Typed composite types use the type converters for parsing and serializing the corresponding tuple values.
 * - Untyped composite types parse and serialize every tuple value to a string (or `null` if the value is `NULL`). Note
 *   that PostgreSQL does not differentiate between `ROW()` and `ROW(NULL)` in their external text representation.
 *   As an untyped composite type cannot tell the original expression, it prefers `ROW()`.
 *
 * @todo throw ParseException on parse errors
 */
abstract class CompositeType implements INamedType
{
	/** @var IType[] ordered map: attribute name => attribute type */
	private $attributes = [];
	/** @var int[] map: attribute name => position of the attribute within the composite type */
	private $attNameMap = [];


	public function __construct()
	{
	}

	/**
	 * Defines a new attribute of this composite type.
	 *
	 * @param string $attName
	 * @param IType $attType
	 */
	public function addAttribute($attName, IType $attType)
	{
		if ((string)$attName == '') {
			$typeName = "{$this->getSchemaName()}.{$this->getName()}";
			$msg = "No attribute name given when adding attribute to composite type $typeName";
			throw new \InvalidArgumentException($msg);
		}
		if (isset($this->attributes[$attName])) {
			$typeName = "{$this->getSchemaName()}.{$this->getName()}";
			throw new \RuntimeException("Attribute '$attName' already defined on composite type $typeName");
		}
		$this->attributes[$attName] = $attType;
		$this->attNameMap[$attName] = count($this->attNameMap);
	}

	/**
	 * @return IType[] ordered map: attribute name => attribute type
	 */
	public function getAttributes()
	{
		return $this->attributes;
	}

	/**
	 * @param string $attName name of an attribute, previously defined by {@link addAttribute()}
	 * @return int|null zero-based position of the given attribute, or <tt>null</tt> if no such attribute is defined
	 */
	public function getAttPos($attName)
	{
		return (isset($this->attNameMap[$attName]) ? $this->attNameMap[$attName] : null);
	}

	public function parseValue($str)
	{
		if ($str == '()' && !$this->attributes) {
			return Composite::fromList($this, []);
		}

		$strLen = strlen($str);
		if ($str[0] != '(' || $str[$strLen - 1] != ')') {
			throw new \InvalidArgumentException('Invalid value for a composite type - not enclosed in parentheses');
		}
		$strOffset = 1;

		$attRegex = '~
		              "(?:[^"\\\\]|""|\\\\.)*"      # either a double-quoted string (backslashes used for escaping, or
		                                            # double quotes doubled for a single double-quote character)
                      |                             # or an unquoted string of characters which do not confuse the
                      (?:[^"()\\\\,]|\\\\.)+        # parser or are backslash-escaped
		             ~x';
		preg_match_all($attRegex, $str, $matches, PREG_PATTERN_ORDER | PREG_OFFSET_CAPTURE, $strOffset);
		$atts = [];
		foreach ($matches[0] as list($att, $attOffset)) {
			for (; $strOffset < $attOffset; $strOffset++) {
				if ($str[$strOffset] == ',') {
					$atts[] = null;
				}
				else {
					$msg = "Invalid value for a composite type - expecting ',' instead of '{$str[$strOffset]}' at offset $strOffset";
					throw new \InvalidArgumentException($msg);
				}
			}
			$cont = ($att[0] == '"' ? substr($att, 1, -1) : $att);
			$atts[] = preg_replace(['~\\\\(.)~', '~""~'], ['$1', '"'], $cont);
			$strOffset += strlen($att);
			if (!($str[$strOffset] == ',' || ($str[$strOffset] == ')' && $strOffset == $strLen - 1))) {
				$msg = "Invalid value for a composite type - expecting ',' instead of '{$str[$strOffset]}' at offset $strOffset";
				throw new \InvalidArgumentException($msg);
			}
			$strOffset++;
		}
		for (; $strOffset < $strLen; $strOffset++) {
			if ($str[$strOffset] == ',' || ($str[$strOffset] == ')' && $strOffset == $strLen - 1)) {
				$atts[] = null;
			}
			else {
				$msg = "Invalid value for a composite type - expecting ',' instead of '{$str[$strOffset]}' at offset $strOffset";
				throw new \InvalidArgumentException($msg);
			}
		}

		/** @var IType[] $types */
		$types = array_values($this->attributes);
		if ($types) {
			if (count($atts) != count($types)) {
				throw new \InvalidArgumentException(sprintf(
					'Invalid number of composite value attributes - expecting %d, parsed %d',
					count($types), count($atts)
				));
			}
			else {
				$values = [];
				foreach ($atts as $i => $v) {
					$values[] = $types[$i]->parseValue($v);
				}
			}
		}
		else {
			$values = $atts;
		}

		return Composite::fromList($this, $values);
	}

	public function serializeValue($val)
	{
		if ($val instanceof Composite) {
			/** @var IType[] $types */
			$types = array_values($val->getType()->getAttributes());
			if ($types) {
				$itemList = $val->toList();
				if (count($itemList) != count($types)) {
					throw new \InvalidArgumentException(sprintf(
						'Invalid number of composite value attributes - expecting %d, given %d',
						count($types), count($itemList)
					));
				}
				$items = [];
				foreach ($itemList as $i => $v) {
					$items[] = $types[$i]->serializeValue($v);
				}
				return self::serializeItems($items);
			}
			else {
				$values = $val->toList();
			}
		}
		elseif (is_array($val)) {
			$values = $val;
		}
		else {
			$message = "Value '$val' is not valid for type {$this->getSchemaName()}.{$this->getName()}";
			throw new \InvalidArgumentException($message);
		}

		$items = [];
		foreach ($values as $v) {
			$items[] = ($v === null ? 'NULL' : "'" . strtr((string)$v, ["'" => "''"]) . "'");
		}
		return self::serializeItems($items);
	}

	private static function serializeItems($items)
	{
		$res = '(' . implode(',', $items) . ')';
		if (count($items) < 2) {
			$res = 'ROW' . $res;
		}
		return $res;
	}
}

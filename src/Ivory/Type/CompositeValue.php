<?php
namespace Ivory\Type;

use Ivory\Exception\ImmutableException;
use Ivory\Exception\UnsupportedException;
use Ivory\Utils\IComparable;

/**
 * A value of a composite type.
 *
 * A composite value is a `Traversable`, readonly-`ArrayAccess`ible list of elementary values. E.g., `$val[0]` contains
 * the value of the first elementary value.
 *
 * If the composite type defines some attributes (using the {@link CompositeType::addAttribute()}, the attribute names
 * are returned as traversal keys; otherwise, the zero-based numeric indices are used for keys. Besides, the attribute
 * names may be used as the `ArrayAccess` keys, if non-numeric, and are also recognized as dynamic properties, exposing
 * the attribute values under their names. E.g., both `$val['foo']` and `$val->foo` refer to the value of attribute
 * named "foo".
 *
 * Note the composite value is immutable, i.e., once constructed, its values cannot be changed. Thus, both `__set()` and
 * `ArrayAccess` write operations ({@link \ArrayAccess::offsetSet()} and {@link \ArrayAccess::offsetUnset()}) throw an
 * {@link \Ivory\Exception\UnsupportedException}.
 */
class CompositeValue implements IComparable, \ArrayAccess, \IteratorAggregate
{
	/** @var CompositeType type of the composite */
	private $type;
	/** @var array list of attribute values */
	private $values;

	/**
	 * Creates a new composite value out of a list of attributes values.
	 *
	 * @param CompositeType $type the type of the value
	 * @param array $values list of values of corresponding attributes
	 * @return CompositeValue
	 */
	public static function fromList(CompositeType $type, $values)
	{
		return new CompositeValue($type, $values);
	}

	/**
	 * Creates a new composite value out of a map of attribute names to the corresponding values.
	 *
	 * Attributes not mentioned in the given map are set to `null`.
	 *
	 * @param NamedCompositeType $type the type of the value; must be a named composite type
	 * @param array $map map: attribute name => value; unspecified attributes get a <tt>null</tt> value
	 * @return CompositeValue
	 */
	public static function fromMap(NamedCompositeType $type, $map)
	{
		$values = array_fill(0, count($type->getAttributes()), null);
		foreach ($map as $k => $v) {
			$pos = $type->getAttPos($k);
			if ($pos !== null) {
				$values[$pos] = $v;
			}
			else {
				$typeName = "{$type->getSchemaName()}.{$type->getName()}";
				$msg = "Error creating a composite value of type $typeName: key '$k' is undefined";
				throw new \InvalidArgumentException($msg);
			}
		}
		return new CompositeValue($type, $values);
	}

	private function __construct(CompositeType $type, $values)
	{
		$this->type = $type;
		$this->values = $values;
	}

	final public function getType()
	{
		return $this->type;
	}

	/**
	 * @param mixed $other
	 * @return bool whether this and the other composite value are of the identical type and contain the same data
	 */
	public function equals($other)
	{
		if (get_class($this) != get_class($other)) {
			return false;
		}
		if ($this->type !== $other->type) {
			return false;
		}
		if (count($this->values) != count($other->values)) {
			return false;
		}

		foreach ($this->values as $i => $v) {
			if (!isset($other->values[$i])) {
				return false;
			}
			if ($v instanceof IComparable) {
				$eq = $v->equals($other->values[$i]);
			}
			else {
				$eq = ($v == $other->values[$i]);
			}
			if (!$eq) {
				return false;
			}
		}

		return true;
	}

	/**
	 * @return array list of the elementary values
	 */
	public function toList()
	{
		return $this->values;
	}

	/**
	 * @return array ordered map: attribute name => attribute value
	 * @throws UnsupportedException if called on a value of an ad hoc composite type, i.e., such that defines no
	 *                              attributes
	 */
	public function toMap()
	{
		$attNames = array_keys($this->type->getAttributes());
		if (!$attNames) {
			$msg = 'Ad hoc composite type value cannot be converted to map - no attributes are defined';
			throw new UnsupportedException($msg);
		}
		$result = [];
		foreach ($this->values as $i => $v) {
			if (isset($attNames[$i])) {
				$k = $attNames[$i];
				$result[$k] = $v;
			}
		}
		return $result;
	}

	//region dynamic properties

	/**
	 * @param string $name attribute name
	 * @return mixed value of the given attribute, or <tt>null</tt> if no such attribute is defined on this value
	 */
	public function __get($name)
	{
		$pos = $this->type->getAttPos($name);
		if ($pos !== null) {
			return $this->values[$pos];
		}
		else {
			return null;
		}
	}

	/**
	 * @param string $name attribute name
	 * @return bool whether the attribute is defined on this value
	 */
	public function __isset($name)
	{
		return ($this->type->getAttPos($name) !== null);
	}

	//endregion

	//region \IArrayAccess

	public function offsetExists($offset)
	{
		if (filter_var($offset, FILTER_VALIDATE_INT) !== false) {
			return ($offset < count($this->values));
		}
		else {
			return ($this->type->getAttPos($offset) !== null);
		}
	}

	public function offsetGet($offset)
	{
		if (filter_var($offset, FILTER_VALIDATE_INT) !== false) {
			return $this->values[$offset];
		}
		else {
			return $this->values[$this->type->getAttPos($offset)];
		}
	}

	public function offsetSet($offset, $value)
	{
		throw new ImmutableException();
	}

	public function offsetUnset($offset)
	{
		throw new ImmutableException();
	}

	//endregion

	//region \IteratorAggregate

	public function getIterator()
	{
		$arr = ($this->type->getAttributes() ? $this->toMap() : $this->values);
		return new \ArrayIterator($arr);
	}

	//endregion
}

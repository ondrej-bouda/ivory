<?php
namespace Ivory\Type;

use Ivory\Exception\NotImplementedException;

abstract class CompositeType implements IType
{
	/** @var IType[] ordered map: attribute name => attribute type */
	private $attributes;


	public function __construct()
	{
	}


	public function parseValue($str)
	{
		throw new NotImplementedException();
	}

	public function serializeValue($val)
	{
		throw new NotImplementedException();
	}

	/**
	 * Defines a new attribute of this composite type.
	 *
	 * @param string $attName
	 * @param IType $attType
	 */
	public function addAttribute($attName, IType $attType)
	{
		$this->attributes[$attName] = $attType;
	}
}

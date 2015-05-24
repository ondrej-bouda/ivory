<?php
namespace Ivory\Type;

abstract class CompositeTypeBase implements IType
{
	/** @var IType[] ordered map: attribute name => attribute type */
	private $attributes;


}

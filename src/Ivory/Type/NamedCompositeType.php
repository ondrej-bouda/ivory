<?php
namespace Ivory\Type;

use Ivory\NamedDbObject;

/**
 * A composite type which has a name. That basically means the type is stored in the database.
 */
class NamedCompositeType extends CompositeType implements INamedType
{
	use NamedDbObject;

	public function __construct($schemaName, $name)
	{
		parent::__construct();
		$this->setName($schemaName, $name);
	}
}

<?php
namespace Ivory\Type;

/**
 * A composite type which has a name. That basically means the type is stored in the database.
 */
class NamedCompositeType extends CompositeTypeBase
{
	private $schemaName;
	private $name;


}

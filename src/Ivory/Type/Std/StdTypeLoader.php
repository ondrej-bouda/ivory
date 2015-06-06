<?php
namespace Ivory\Type\Std;

class StdTypeLoader implements \Ivory\Type\ITypeLoader
{
	const PG_CATALOG = 'pg_catalog';

	public function loadType($typeName, $schemaName, \Ivory\IConnection $connection)
	{
		if ($schemaName != self::PG_CATALOG) {
			return null;
		}

		switch (strtoupper(trim($typeName))) {
			case 'INTEGER':
			case 'INT':
			case 'INT4':
			case 'SMALLINT':
			case 'INT2':
				return new Integer($typeName, $schemaName);

			case 'BIGINT':
			case 'INT8':
				return new BigInt($typeName, $schemaName);

			case 'BOOLEAN':
			case 'BOOL':
				return new Boolean($typeName, $schemaName);

			case 'TEXT':
			case 'CHARACTER':
			case 'CHAR':
			case 'CHARACTER VARYING':
			case 'VARCHAR':
			case 'BPCHAR':
				return new String($typeName, $schemaName);

			case 'BYTEA':
				return new Binary($typeName, $schemaName);

			case 'BIT':
				return new FixedBitString($typeName, $schemaName);

			case 'BIT VARYING':
			case 'VARBIT':
				return new VarBitString($typeName, $schemaName);

			default:
				return null;
		}
	}
}

<?php
namespace Ivory\Type\Std;

use Ivory\Value\LineSegment;

class StdTypeLoader implements \Ivory\Type\ITypeLoader
{
	const PG_CATALOG = 'pg_catalog';

	public function loadType($typeName, $schemaName, \Ivory\IConnection $connection)
	{
		if ($schemaName != self::PG_CATALOG) {
			return null;
		}

		switch (strtoupper(trim($typeName))) {
			case 'SMALLINT':
			case 'INT2':
			case 'INTEGER':
			case 'INT':
			case 'INT4':
				return new IntegerType($typeName, $schemaName);

			case 'BIGINT':
			case 'INT8':
				return new BigIntType($typeName, $schemaName);

			case 'NUMERIC':
			case 'DECIMAL':
				return new DecimalType($typeName, $schemaName);

			case 'REAL':
			case 'FLOAT4':
			case 'DOUBLE PRECISION':
			case 'FLOAT8':
				return new FloatType($typeName, $schemaName);

			case 'BOOLEAN':
			case 'BOOL':
				return new BooleanType($typeName, $schemaName);

			case 'TEXT':
			case 'CHARACTER':
			case 'CHAR':
			case 'CHARACTER VARYING':
			case 'VARCHAR':
			case 'BPCHAR':
				return new StringType($typeName, $schemaName);

			case 'BYTEA':
				return new BinaryType($typeName, $schemaName);

			case 'BIT':
				return new FixedBitStringType($typeName, $schemaName);

			case 'BIT VARYING':
			case 'VARBIT':
				return new VarBitStringType($typeName, $schemaName);

			case 'JSON':
				return new JsonExactType($typeName, $schemaName);

			case 'JSONB':
				return new JsonBType($typeName, $schemaName);

			case 'XML':
				return new XmlType($typeName, $schemaName);

			case 'UUID':
				return new UuidType($typeName, $schemaName);

			case 'POINT':
				return new PointType($typeName, $schemaName);
			case 'LINE':
				return new LineType($typeName, $schemaName);
			case 'LSEG':
				return new LineSegmentType($typeName, $schemaName);
			case 'BOX':
				return new BoxType($typeName, $schemaName);
			case 'PATH':
				return new PathType($typeName, $schemaName);
			case 'POLYGON':
				return new PolygonType($typeName, $schemaName);
			case 'CIRCLE':
				return new CircleType($typeName, $schemaName);

			default:
				return null;
		}
	}
}

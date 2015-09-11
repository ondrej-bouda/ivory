<?php
namespace Ivory\Type\Std;

class StdTypeLoader implements \Ivory\Type\ITypeLoader
{
	const PG_CATALOG = 'pg_catalog';

	public function loadType($typeName, $schemaName, \Ivory\Connection\IConnection $connection)
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
				return new IntegerType($typeName, $schemaName, $connection);

			case 'BIGINT':
			case 'INT8':
				return new BigIntType($typeName, $schemaName, $connection);

			case 'NUMERIC':
			case 'DECIMAL':
				return new DecimalType($typeName, $schemaName, $connection);

			case 'REAL':
			case 'FLOAT4':
			case 'DOUBLE PRECISION':
			case 'FLOAT8':
				return new FloatType($typeName, $schemaName, $connection);

			case 'BOOLEAN':
			case 'BOOL':
				return new BooleanType($typeName, $schemaName, $connection);

			case 'TEXT':
			case 'CHARACTER':
			case 'CHAR':
			case 'CHARACTER VARYING':
			case 'VARCHAR':
			case 'BPCHAR':
				return new StringType($typeName, $schemaName, $connection);

			case 'BYTEA':
				return new BinaryType($typeName, $schemaName, $connection);

			case 'BIT':
				return new FixedBitStringType($typeName, $schemaName, $connection);

			case 'BIT VARYING':
			case 'VARBIT':
				return new VarBitStringType($typeName, $schemaName, $connection);

			case 'JSON':
				return new JsonExactType($typeName, $schemaName, $connection);
			case 'JSONB':
				return new JsonBType($typeName, $schemaName, $connection);

			case 'XML':
				return new XmlType($typeName, $schemaName, $connection);

			case 'UUID':
				return new UuidType($typeName, $schemaName, $connection);

			case 'POINT':
				return new PointType($typeName, $schemaName, $connection);
			case 'LINE':
				return new LineType($typeName, $schemaName, $connection);
			case 'LSEG':
				return new LineSegmentType($typeName, $schemaName, $connection);
			case 'BOX':
				return new BoxType($typeName, $schemaName, $connection);
			case 'PATH':
				return new PathType($typeName, $schemaName, $connection);
			case 'POLYGON':
				return new PolygonType($typeName, $schemaName, $connection);
			case 'CIRCLE':
				return new CircleType($typeName, $schemaName, $connection);

			case 'INET':
				return new InetType($typeName, $schemaName, $connection);
			case 'CIDR':
				return new CidrType($typeName, $schemaName, $connection);
			case 'MACADDR':
				return new MacAddrType($typeName, $schemaName, $connection);

			case 'MONEY':
				return new MoneyType($typeName, $schemaName, $connection);

			default:
				return null;
		}
	}
}

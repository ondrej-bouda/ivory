<?php
namespace Ivory\Type\Std;

class StdTypeLoader implements \Ivory\Type\ITypeLoader
{
	public function loadType($schemaName, $typeName, \Ivory\Connection\IConnection $connection)
	{
		if ($schemaName != 'pg_catalog') {
			return null;
		}

		switch ($typeName) {
			case 'smallint':
			case 'int2':
			case 'integer':
			case 'int':
			case 'int4':
				return new IntegerType($typeName, $schemaName, $connection);

			case 'bigint':
			case 'int8':
				return new BigIntType($typeName, $schemaName, $connection);

			case 'numeric':
			case 'decimal':
				return new DecimalType($typeName, $schemaName, $connection);

			case 'real':
			case 'float4':
			case 'double precision':
			case 'float8':
				return new FloatType($typeName, $schemaName, $connection);

			case 'boolean':
			case 'bool':
				return new BooleanType($typeName, $schemaName, $connection);

			case 'text':
			case 'character':
			case 'char':
			case 'character varying':
			case 'varchar':
			case 'bpchar':
				return new StringType($typeName, $schemaName, $connection);

			case 'bytea':
				return new BinaryType($typeName, $schemaName, $connection);

			case 'bit':
				return new FixedBitStringType($typeName, $schemaName, $connection);

			case 'bit varying':
			case 'varbit':
				return new VarBitStringType($typeName, $schemaName, $connection);

			case 'json':
				return new JsonExactType($typeName, $schemaName, $connection);
			case 'jsonb':
				return new JsonBType($typeName, $schemaName, $connection);

			case 'xml':
				return new XmlType($typeName, $schemaName, $connection);

			case 'uuid':
				return new UuidType($typeName, $schemaName, $connection);

			case 'point':
				return new PointType($typeName, $schemaName, $connection);
			case 'line':
				return new LineType($schemaName, $typeName, $connection);
			case 'lseg':
				return new LineSegmentType($typeName, $schemaName, $connection);
			case 'box':
				return new BoxType($typeName, $schemaName, $connection);
			case 'path':
				return new PathType($typeName, $schemaName, $connection);
			case 'polygon':
				return new PolygonType($typeName, $schemaName, $connection);
			case 'circle':
				return new CircleType($typeName, $schemaName, $connection);

			case 'inet':
				return new InetType($typeName, $schemaName, $connection);
			case 'cidr':
				return new CidrType($typeName, $schemaName, $connection);
			case 'macaddr':
				return new MacAddrType($typeName, $schemaName, $connection);

			case 'money':
				return new MoneyType($typeName, $schemaName, $connection);

			default:
				return null;
		}
	}
}

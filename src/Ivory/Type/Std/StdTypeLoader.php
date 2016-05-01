<?php
namespace Ivory\Type\Std;

use Ivory\Type\AdHocCompositeType;

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
				return new IntegerType($schemaName, $typeName, $connection);

			case 'bigint':
			case 'int8':
				return new BigIntType($schemaName, $typeName, $connection);

			case 'numeric':
			case 'decimal':
				return new DecimalType($schemaName, $typeName, $connection);

			case 'real':
			case 'float4':
			case 'double precision':
			case 'float8':
				return new FloatType($schemaName, $typeName, $connection);

			case 'boolean':
			case 'bool':
				return new BooleanType($schemaName, $typeName, $connection);

			case 'text':
			case 'character':
			case 'char':
			case 'character varying':
			case 'varchar':
			case 'bpchar':
			case 'unknown':
			case 'cstring':
				return new StringType($schemaName, $typeName, $connection);

			case 'date':
				return new DateType($schemaName, $typeName, $connection);
			case 'time':
				return new TimeType($schemaName, $typeName, $connection);
			case 'timetz':
				return new TimeTzType($schemaName, $typeName, $connection);
			case 'timestamp':
				return new TimestampType($schemaName, $typeName, $connection);
			case 'timestamptz':
				return new TimestampTzType($schemaName, $typeName, $connection);

			case 'bytea':
				return new BinaryType($schemaName, $typeName, $connection);

			case 'bit':
				return new FixedBitStringType($schemaName, $typeName, $connection);

			case 'bit varying':
			case 'varbit':
				return new VarBitStringType($schemaName, $typeName, $connection);

			case 'json':
				return new JsonExactType($schemaName, $typeName, $connection);
			case 'jsonb':
				return new JsonBType($schemaName, $typeName, $connection);

			case 'xml':
				return new XmlType($schemaName, $typeName, $connection);

			case 'uuid':
				return new UuidType($schemaName, $typeName, $connection);

			case 'point':
				return new PointType($schemaName, $typeName, $connection);
			case 'line':
				return new LineType($schemaName, $typeName, $connection);
			case 'lseg':
				return new LineSegmentType($schemaName, $typeName, $connection);
			case 'box':
				return new BoxType($schemaName, $typeName, $connection);
			case 'path':
				return new PathType($schemaName, $typeName, $connection);
			case 'polygon':
				return new PolygonType($schemaName, $typeName, $connection);
			case 'circle':
				return new CircleType($schemaName, $typeName, $connection);

			case 'inet':
				return new InetType($schemaName, $typeName, $connection);
			case 'cidr':
				return new CidrType($schemaName, $typeName, $connection);
			case 'macaddr':
				return new MacAddrType($schemaName, $typeName, $connection);

			case 'money':
				return new MoneyType($schemaName, $typeName, $connection);

			case 'pg_lsn':
				return new PgLsnType($schemaName, $typeName, $connection);
			case 'txid_snapshot':
				return new TxIdSnapshotType($schemaName, $typeName, $connection);

			case 'void':
				return new VoidType($schemaName, $typeName, $connection);

			case 'record':
				return new AdHocCompositeType($schemaName, $typeName);

			case 'any':
			case 'anyelement':
			case 'anyarray':
			case 'anynonarray':
			case 'anyenum':
			case 'anyrange':
				return new PolymorphicPseudoType($schemaName, $typeName, $connection);

			default:
				return null;
		}
	}
}

<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Lang\Sql\Types;
use Ivory\Type\IType;
use Ivory\Type\ITypeLoader;
use Ivory\Type\Postgresql\RecordType;

/**
 * Type loader for the standard PostgreSQL base types.
 */
class StdTypeLoader implements ITypeLoader
{
    public function loadType(string $schemaName, string $typeName): ?IType
    {
        switch ($schemaName) {
            case 'pg_catalog':
                switch ($typeName) {
                    case 'smallint':
                    case 'int2':
                    case 'integer':
                    case 'int':
                    case 'int4':
                        return new IntegerType($schemaName, $typeName);

                    case 'bigint':
                    case 'int8':
                        return BigIntSafeType::createForRange(
                            Types::BIGINT_MIN, Types::BIGINT_MAX,
                            $schemaName, $typeName
                        );

                    case 'numeric':
                    case 'decimal':
                        return new DecimalType($schemaName, $typeName);

                    case 'real':
                    case 'float4':
                    case 'double precision':
                    case 'float8':
                        return new FloatType($schemaName, $typeName);

                    case 'boolean':
                    case 'bool':
                        return new BooleanType($schemaName, $typeName);

                    case 'text':
                    case 'character':
                    case 'char':
                    case 'character varying':
                    case 'varchar':
                    case 'bpchar':
                    case 'unknown':
                    case 'cstring':
                        return new StringType($schemaName, $typeName);

                    case 'date':
                        return new DateType($schemaName, $typeName);
                    case 'time':
                        return new TimeType($schemaName, $typeName);
                    case 'timetz':
                        return new TimeTzType($schemaName, $typeName);
                    case 'timestamp':
                        return new TimestampType($schemaName, $typeName);
                    case 'timestamptz':
                        return new TimestampTzType($schemaName, $typeName);
                    case 'interval':
                        return new IntervalType($schemaName, $typeName);

                    case 'bytea':
                        return new BinaryType($schemaName, $typeName);

                    case 'bit':
                        return new FixedBitStringType($schemaName, $typeName);

                    case 'bit varying':
                    case 'varbit':
                        return new VarBitStringType($schemaName, $typeName);

                    case 'json':
                        return new JsonExactType($schemaName, $typeName);
                    case 'jsonb':
                        return new JsonBType($schemaName, $typeName);

                    case 'xml':
                        return new XmlType($schemaName, $typeName);

                    case 'uuid':
                        return new UuidType($schemaName, $typeName);

                    case 'point':
                        return new PointType($schemaName, $typeName);
                    case 'line':
                        return new LineType($schemaName, $typeName);
                    case 'lseg':
                        return new LineSegmentType($schemaName, $typeName);
                    case 'box':
                        return new BoxType($schemaName, $typeName);
                    case 'path':
                        return new PathType($schemaName, $typeName);
                    case 'polygon':
                        return new PolygonType($schemaName, $typeName);
                    case 'circle':
                        return new CircleType($schemaName, $typeName);

                    case 'inet':
                        return new InetType($schemaName, $typeName);
                    case 'cidr':
                        return new CidrType($schemaName, $typeName);
                    case 'macaddr':
                        return new MacAddrType($schemaName, $typeName);

                    case 'money':
                        return new MoneyType($schemaName, $typeName);

                    case 'pg_lsn':
                        return new PgLsnType($schemaName, $typeName);
                    case 'txid_snapshot':
                        return new TxIdSnapshotType($schemaName, $typeName);

                    case 'tsvector':
                        return new TsVectorType($schemaName, $typeName);
                    case 'tsquery':
                        return new TsQueryType($schemaName, $typeName);

                    case 'void':
                        return new VoidType($schemaName, $typeName);

                    case 'record':
                        return new RecordType($schemaName, $typeName);

                    case 'any':
                    case 'anyelement':
                    case 'anyarray':
                    case 'anynonarray':
                    case 'anyenum':
                    case 'anyrange':
                        return new PolymorphicPseudoType($schemaName, $typeName);
                }
                break;

            case 'public':
                switch ($typeName) {
                    case 'hstore':
                        return new HstoreType($schemaName, $typeName);
                }
                break;
        }

        return null;
    }
}

<?php
declare(strict_types=1);
namespace Ivory\Type;

use Ivory\Exception\InternalException;
use Ivory\Type\Ivory\UndefinedType;
use Ivory\Type\Ivory\UnorderedRangeType;
use Ivory\Type\Postgresql\CompositeType;

class IntrospectingTypeDictionaryCompiler extends TypeDictionaryCompilerBase
{
    private $connHandler;

    public function __construct($connHandler)
    {
        $this->connHandler = $connHandler;
    }

    protected function yieldTypes(ITypeProvider $typeProvider, ITypeDictionary $dict): \Generator
    {
        $compositeAttrMap = [];
        foreach ($this->queryTypes() as $row) {
            $schemaName = $row['nspname'];
            $typeName = $row['typname'];

            if ($row['composite_attributes']) {
                $attrs = json_decode($row['composite_attributes']);
                if (is_array($attrs)) {
                    $compositeAttrMap[$row['oid']] = $attrs;
                } else {
                    $err = (json_last_error_msg() ?: '?');
                    throw new \RuntimeException(
                        "Error decoding attributes of composite type `$schemaName`.`$typeName`: $err"
                    );
                }
            }

            /* OPT: This leads to a great flexibility - any type may be registered explicitly, bypassing the generic
             *      type constructors offered by Ivory. It may as well lead to a performance penalty, though.
             */
            $providedType = $typeProvider->provideType($schemaName, $typeName);
            if ($providedType !== null) {
                $type = clone $providedType; // clone: do not touch the original object - it might be reused by others
            } else {
                $type = $this->createTypeFromRow($row, $typeProvider, $dict);
            }

            yield (int)$row['oid'] => $type;
        }

        $this->addCompositeAttributes($dict, $compositeAttrMap);
    }

    private function queryTypes(): \Generator
    {
        $query = <<<'SQL'
WITH RECURSIVE typeinfo (oid, nspname, typname, typtype, parenttype, arrelemtypdelim) AS (
    SELECT
        t.oid,
        nsp.nspname,
        t.typname,
        (CASE WHEN arrelemtype.oid IS NOT NULL THEN 'A' ELSE t.typtype END), -- marking array types as of typtype 'A'
        (CASE WHEN arrelemtype.oid IS NOT NULL THEN arrelemtype.oid
              WHEN t.typtype = 'd' THEN t.typbasetype
              WHEN t.typtype = 'r' THEN rngsubtype
         END),
        arrelemtype.typdelim,
        (
            CASE WHEN t.typtype = 'e' THEN
                (
                    SELECT to_json(array_agg(enumlabel ORDER BY enumsortorder))
                    FROM pg_catalog.pg_enum
                    WHERE enumtypid = t.oid
                )
            END
        ) AS enum_labels,
        (
            CASE WHEN t.typtype = 'c' THEN
                (
                    SELECT json_agg(ARRAY[attname::TEXT, atttypid::TEXT] ORDER BY attnum)
                    FROM pg_catalog.pg_attribute
                    WHERE
                        attrelid = t.typrelid AND
                        attnum > 0 AND
                        NOT attisdropped
                )
            END
        ) AS composite_attributes
    FROM
        pg_catalog.pg_type t
        JOIN pg_catalog.pg_namespace nsp ON nsp.oid = t.typnamespace
        LEFT JOIN pg_catalog.pg_range rng ON rng.rngtypid = t.oid
        LEFT JOIN pg_catalog.pg_type arrelemtype ON arrelemtype.typarray = t.oid
),
typetree (oid, depth) AS (
    SELECT oid, 0 FROM typeinfo WHERE parenttype IS NULL
    UNION ALL
    SELECT ti.oid, tt.depth + 1
    FROM
        typeinfo ti
        JOIN typetree tt ON tt.oid = ti.parenttype
)
SELECT ti.*
FROM
    typeinfo ti
    JOIN typetree tt USING (oid)
ORDER BY tt.depth
SQL;
        /* NOTE: The query sorts the types so that, when processing them one by one, there are no forward references.
                 This is achieved by constituting the types dependency tree, and ordering the types by the depth in the
                 tree. For such method to be correct, we should prove the dependency graph among types actually forms
                 a tree, or more precisely, a forest. See the following points:
                 1) There are only four types of types which refer other types and thus might be involved in a cycle:
                    arrays, ranges, domains, and composites.
                 2) Neither of these types may be created above a shell type. Their creation must thus only be made
                    above existing, fully-defined types.
                 3) Neither arrays, ranges, nor domains can be altered to be defined on a different type - a drop is
                    required. Thus, considering 1), any circular dependency must involve a composite type.
                 4) In a circular dependency only involving composite, array and domain types, the composite type would
                    be a member of itself, which is checked and rejected by PostgreSQL.
                 5) Thus, the only possibility for a circular dependency is such that it contains a composite and a
                    range type. Which, indeed, is possible:
                      CREATE TYPE c AS (a INT);
                      CREATE TYPE r AS RANGE (SUBTYPE = c);
                      ALTER TYPE c ADD ATTRIBUTE rng r;
                 That said, the dependency graph does not form a forest, but that's only due to attributes of composite
                 types. However, the composite type in a circular dependency may only be referred to as a whole.
                 Therefore, the solution to compiling a type dictionary is such that only empty composite types are
                 created at first, then their attributes are added to them. For such proceeding, (empty) composite types
                 are considered as having no dependencies. The rest of types form a dependency tree, as follows from
                 point 3) and from the fact that no type (except composites) may depend on more than one other type.
                 Moreover, to be correct, we should prove that ordering the dependency forest by the tree depth suffices
                 for the ordered result set not to contain any forward references. That's an easy induction, though:
                 i)  Types in the level 0 have no dependencies, and thus may all be processed first, in any order.
                 ii) Types in the level N+1 only have dependencies on types in the level N, which have already been
                     processed, and thus all the (N+1)-level types may be processed, in any order.
         */

        $result = pg_query($this->connHandler, $query);
        if (!is_resource($result) && !$result instanceof \PgSql\Result) {
            throw new \RuntimeException("Error fetching types: " . pg_last_error($this->connHandler));
        }
        /** @noinspection PhpStrictComparisonWithOperandsOfDifferentTypesInspection pg_fetch_assoc() may return FALSE */
        /** @noinspection PhpAssignmentInConditionInspection it's a pity explicit parentheses do not suffice */
        while (($row = pg_fetch_assoc($result)) !== false) {
            yield $row;
        }
    }

    private function createTypeFromRow(array $row, ITypeProvider $typeProvider, ITypeDictionary $dict): IType
    {
        $schemaName = $row['nspname'];
        $typeName = $row['typname'];

        switch ($row['typtype']) {
            case 'A':
                $elemType = $dict->requireTypeByOid((int)$row['parenttype']);
                assert($elemType instanceof IType,
                    new InternalException('Only named types are supposed to be used as array element types.')
                );
                return $this->createArrayType($elemType, $row['arrelemtypdelim']);
                // NOTE: typdelim of the array type itself seems irrelevant

            case 'b':
            case 'p': // treating pseudo-types as base types - they must be recognized by Ivory
                return $this->createBaseType($schemaName, $typeName, $typeProvider);
                // OPT: types not recognized by the type provider are queried twice

            case 'c':
                return $this->createCompositeType($schemaName, $typeName); // attributes added later

            case 'd':
                $baseType = $dict->requireTypeByOid((int)$row['parenttype']);
                assert($baseType instanceof IType,
                    new InternalException('Only named types are supposed to be used as domain base types.')
                );
                return $this->createDomainType($schemaName, $typeName, $baseType);

            case 'e':
                $labels = ($row['enum_labels'] ? json_decode($row['enum_labels']) : []);
                if (!is_array($labels)) {
                    $err = (json_last_error_msg() ?: '?');
                    throw new \RuntimeException(
                        "Error decoding labels of enum type `$schemaName`.`$typeName`: $err"
                    );
                }
                return $this->createEnumType($schemaName, $typeName, $labels);

            case 'r':
                $subtype = $dict->requireTypeByOid((int)$row['parenttype']);
                if ($subtype instanceof ITotallyOrderedType) {
                    return $this->createRangeType($schemaName, $typeName, $subtype);
                } elseif ($subtype instanceof UndefinedType) {
                    return new UndefinedType($schemaName, $typeName);
                } else {
                    return new UnorderedRangeType($schemaName, $typeName);
                }

            case 'm':
                return new UndefinedType($schemaName, $typeName);

            default:
                throw new \RuntimeException("Error fetching types: unexpected typtype '$row[typtype]'");
        }
    }

    private function addCompositeAttributes(ITypeDictionary $dict, array $attrMap): void
    {
        foreach ($attrMap as $compTypeOid => $attrs) {
            $compType = $dict->requireTypeByOid($compTypeOid);
            assert($compType instanceof CompositeType);
            foreach ($attrs as [$attrName, $attrTypeOid]) {
                $attrType = $dict->requireTypeByOid((int)$attrTypeOid);
                $compType->addAttribute($attrName, $attrType);
            }
        }
    }
}

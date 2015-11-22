<?php
namespace Ivory\Type;

class IntrospectingTypeDictionaryCompiler implements ITypeDictionaryCompiler
{
    private $connHandler;

    public function __construct($connHandler)
    {
        $this->connHandler = $connHandler;
    }

    public function compileTypeDictionary(ITypeProvider $typeProvider)
    {
        $dict = new TypeDictionary();

        $enumLabels = $this->retrieveEnumLabels();

        $query = "WITH RECURSIVE typeinfo (oid, nspname, typname, typtype, parenttype, arrelemtypdelim) AS (
                    SELECT t.oid, nspname, t.typname,
                           (CASE WHEN arrelemtype.oid IS NOT NULL THEN 'A' ELSE t.typtype END), -- marking array types as of typtype 'A'
                           (CASE WHEN arrelemtype.oid IS NOT NULL THEN arrelemtype.oid
                                 WHEN t.typtype = 'd' THEN t.typbasetype
                                 WHEN t.typtype = 'r' THEN rngsubtype
                            END),
                           arrelemtype.typdelim
                    FROM pg_catalog.pg_type t
                         JOIN pg_catalog.pg_namespace nsp ON nsp.oid = t.typnamespace
                         LEFT JOIN pg_catalog.pg_range rng ON rng.rngtypid = t.oid
                         LEFT JOIN pg_catalog.pg_type arrelemtype ON arrelemtype.typarray = t.oid
                  ),
                  typetree (oid, depth) AS (
                    SELECT oid, 0 FROM typeinfo WHERE parenttype IS NULL
                    UNION ALL
                    SELECT ti.oid, tt.depth + 1
                    FROM typeinfo ti
                         JOIN typetree tt ON tt.oid = ti.parenttype
                  )
                  SELECT ti.*
                  FROM typeinfo ti
                       JOIN typetree tt USING (oid)
                  ORDER BY tt.depth";
        /* NOTE: The query orders the types so that, when processing them one by one, there are no forward references.
                 This is achieved by constituting the types dependency tree, and ordering the types by the depth in the
                 tree. For such method to be correct, we should prove the dependency graph among types is actually forms
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
                 types. However, the composite type in a circular dependency may only be referred as a whole.
                 Therefore, the solution to compiling a type dictionary is such that only empty composite types are
                 created at first, then their attributes are added to them. For such proceeding, (empty) composite types
                 are considered as having no dependencies. The rest of types form a dependency tree, as follows from
                 point 3) and from the fact that no type (except composites) may depend no more than one other type.
                 Moreover, to be correct, we should prove that ordering the dependency forest by the tree depth suffices
                 for the ordered result set not to contain any forward references. That's an easy induction, though:
                 i)  Types in the level 0 have no dependencies, and thus may all be processed first, in any order.
                 ii) Types in the level N+1 only have dependencies on types in the level N, which have already been
                     processed, and thus all the (N+1)-level types may be processed, in any order.
         */
        $errorDesc = 'Error fetching types';
        foreach ($this->query($query, $errorDesc) as $row) {
            $schemaName = $row['nspname'];
            $typeName = $row['typname'];
            switch ($row['typtype']) {
                case 'A':
                    $elemType = $dict->requireTypeFromOid($row['parenttype']);
                    $type = $this->createArrayType($elemType, $row['arrelemtypdelim']);
                    // NOTE: typdelim of the array type itself seems irrelevant
                    break;
                case 'b':
                    $type = $this->createBaseType($schemaName, $typeName, $typeProvider);
                    break;
                case 'c':
                    $type = $this->createCompositeType($schemaName, $typeName); // attributes added later
                    break;
                case 'd':
                    $baseType = $dict->requireTypeFromOid($row['parenttype']);
                    $type = $this->createDomainType($schemaName, $typeName, $baseType);
                    break;
                case 'e':
                    $labels = (isset($enumLabels[$row['oid']]) ? $enumLabels[$row['oid']] : []);
                    $type = $this->createEnumType($schemaName, $typeName, $labels);
                    break;
                case 'p':
                    $type = $this->createPseudoType($schemaName, $typeName);
                    break;
                case 'r':
                    $subtype = $dict->requireTypeFromOid($row['parenttype']);
                    $type = $this->createRangeType($schemaName, $typeName, $subtype);
                    break;
                default:
                    throw new \RuntimeException("Error fetching types: unexpected typtype '$row[typtype]'");
            }

            $dict->defineType($row['oid'], $type);
        }

        $this->fetchCompositeAttributes($dict);

        return $dict;
    }

    private function retrieveEnumLabels()
    {
        $query = 'SELECT enumtypid, enumlabel
                  FROM pg_catalog.pg_enum
                  ORDER BY enumtypid, enumsortorder';
        $errorDesc = 'Error fetching enum labels';
        $labels = [];
        foreach ($this->query($query, $errorDesc) as $row) {
            $oid = $row['enumtypid'];
            if (!isset($labels[$oid])) {
                $labels[$oid] = [];
            }
            $labels[$oid][] = $row['enumlabel'];
        }
        return $labels;
    }

    private function fetchCompositeAttributes(ITypeDictionary $dict)
    {
        $query = 'SELECT pg_type.oid, attname, atttypid
                  FROM pg_catalog.pg_attribute
                       JOIN pg_catalog.pg_type ON typrelid = attrelid
                  WHERE attnum > 0 AND NOT attisdropped
                  ORDER BY attrelid, attnum';
        $errorDesc = 'Error fetching composite type attributes';
        foreach ($this->query($query, $errorDesc) as $row) {
            /** @var CompositeType $compType */
            $compType = $dict->requireTypeFromOid($row['oid']);
            $attType = $dict->requireTypeFromOid($row['atttypid']);
            $compType->addAttribute($row['attname'], $attType);
        }
    }

    private function query($query, $errorDesc)
    {
        $result = pg_query($this->connHandler, $query);
        if (!is_resource($result)) {
            throw new \RuntimeException("$errorDesc: " . pg_last_error($this->connHandler));
        }
        while (($row = pg_fetch_assoc($result)) !== false) {
            yield $row;
        }
    }

    /**
     * @param INamedType $elemType
     * @param string $delimiter
     * @return IType
     */
    protected function createArrayType(INamedType $elemType, $delimiter)
    {
        return new ArrayType($elemType, $delimiter);
    }

    /**
     * @param string $schemaName
     * @param string $typeName
     * @param ITypeProvider $typeProvider
     * @return IType
     */
    protected function createBaseType($schemaName, $typeName, ITypeProvider $typeProvider)
    {
        return $typeProvider->requireBaseType($schemaName, $typeName);
    }

    /**
     * @param string $schemaName
     * @param string $typeName
     * @return IType
     */
    protected function createCompositeType($schemaName, $typeName)
    {
        return new NamedCompositeType($schemaName, $typeName);
    }

    /**
     * @param string $schemaName
     * @param string $typeName
     * @param IType $baseType the domain base type
     * @return IType
     */
    protected function createDomainType($schemaName, $typeName, IType $baseType)
    {
        return new DomainType($schemaName, $typeName, $baseType);
    }

    /**
     * @param string $schemaName
     * @param string $typeName
     * @param string[] $labels list of enumeration labels in the definition order
     * @return IType
     */
    private function createEnumType($schemaName, $typeName, $labels)
    {
        return new EnumType($schemaName, $typeName, $labels);
    }

    /**
     * @param string $schemaName
     * @param string $typeName
     * @return IType
     */
    protected function createPseudoType($schemaName, $typeName)
    {
        return null; // FIXME
    }

    /**
     * @param string $schemaName
     * @param string $typeName
     * @param IType $subtype the range subtype
     * @return IType
     */
    protected function createRangeType($schemaName, $typeName, IType $subtype)
    {
        return new RangeType($schemaName, $typeName, $subtype);
    }
}

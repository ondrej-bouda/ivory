-- databases
SELECT datname, datdba, pg_catalog.shobj_description(d.oid, 'pg_database') AS comment
FROM   pg_catalog.pg_database d

;

-- extensions
SELECT oid, extname, extversion
FROM pg_catalog.pg_extension;

-- schemas
SELECT oid, nspname, obj_description( oid ) AS comment
FROM pg_namespace
WHERE nspname <> 'information_schema' AND substr( nspname, 0, 4 ) <> 'pg_'

;

-- relations: tables, indices, sequences, (materialized) views, composite types, TOAST tables, and foreign tables
SELECT cl.oid, ns.nspname AS namespace_name, cl.relname,
  cl.relkind,
               (CASE cl.relkind WHEN 'r' THEN 'ordinary table'
                WHEN 'i' THEN 'index'
                WHEN 'S' THEN 'sequence'
                WHEN 'v' THEN 'view'
                WHEN 'm' THEN 'materialized view'
                WHEN 'c' THEN 'composite type'
                WHEN 't' THEN 'TOAST table'
                WHEN 'f' THEN 'foreign table'
                ELSE '?'
                END) AS relkind_meaning,
               obj_description( cl.oid ) AS comment,
               pg_type_reltype.typname AS reltype_name, -- useful?
               pg_type_reloftype.typname AS reloftype_name, -- useful?
               pg_am.amname AS index_access_method,
               (CASE WHEN cl.relkind = 'v' THEN pg_get_viewdef(cl.oid) ELSE NULL END) AS view_definition
FROM pg_class cl
  JOIN pg_namespace ns ON ns.oid = cl.relnamespace
  LEFT JOIN pg_am ON pg_am.oid = cl.relam
  LEFT JOIN pg_type pg_type_reltype ON pg_type_reltype.oid = cl.reltype
  LEFT JOIN pg_type pg_type_reloftype ON pg_type_reloftype.oid = cl.reloftype
WHERE nspname <> 'information_schema' AND substr( nspname, 0, 4 ) <> 'pg_' AND
      cl.relpersistence = 'p' -- only permanent relations are of our interest (but unlogged should be, too)

;

-- sequence properties
SELECT start_value, last_value, increment_by, max_value, min_value, cache_value, is_cycled
FROM "public"."example_id_seq";

;

-- sequences
SELECT cl.oid, cl.relname, ns.nspname as schema, obj_description( cl.oid ) AS comment
FROM pg_class cl
  JOIN pg_namespace ns ON ns.oid=relnamespace
WHERE cl.relkind = 'S' AND nspname <> 'information_schema' AND substr( nspname, 0, 4 ) <> 'pg_'

;

-- functions
SELECT n.nspname AS schema, proname, p.proargtypes,
  typname, lanname, p.oid,
       pg_get_functiondef(p.oid) as text, obj_description(p.oid) AS comment,
       (SELECT CASE WHEN p.proallargtypes IS NULL
         THEN array_to_string(
             array(
                 SELECT t.typname
                 FROM pg_type t
                   JOIN (SELECT i
                         FROM (SELECT generate_series(array_lower(p.proargtypes, 1), array_upper(p.proargtypes, 1))) g(i)
                        ) sub ON p.proargtypes[sub.i] = t.oid
                 ORDER BY sub.i
             ),
             ' '
         )
               ELSE array_to_string(
                   array(
                       SELECT t.typname
                       FROM pg_type t
                         JOIN (SELECT i
                               FROM (SELECT generate_series(array_lower(p.proallargtypes, 1), array_upper(p.proallargtypes, 1))) g(i)
                              ) sub ON p.proallargtypes[sub.i] = t.oid
                       ORDER BY sub.i
                   ),
                   ' '
               )
               END
       ) AS argtypenames,
       array_to_string(
           array(
               SELECT t.typname
               FROM pg_type t
                 JOIN ( SELECT i FROM ( SELECT generate_series( array_lower( p.proargtypes, 1 ), array_upper( p.proargtypes, 1 ) ) ) g( i ) ) sub
                   ON  p.proargtypes[sub.i] = t.oid
               ORDER BY sub.i
           ),
           ' '
       ) AS argsignature,
  p.proargmodes
FROM pg_catalog.pg_proc p
  JOIN pg_catalog.pg_namespace n ON p.pronamespace = n.oid
  JOIN pg_catalog.pg_language l ON p.prolang = l.oid
  JOIN pg_catalog.pg_type t ON p.prorettype = t.oid
WHERE NOT proisagg AND nspname <> 'information_schema' AND substr( nspname, 0, 4 ) <> 'pg_'

;

-- foreign keys (not working, though)
SELECT c.oid, c.conname AS constraint_name, ns.nspname AS schema, obj_description( c.oid ) AS comment,
  confdeltype, confupdtype, confmatchtype, cls_c.relname AS table_name, cls_p.relname AS foreign_table_name
FROM pg_constraint c
  JOIN pg_namespace ns ON ns.oid = c.connamespace
  JOIN pg_class cls_c ON cls_c.oid = c.conrelid
  JOIN pg_namespace ns_c ON ns_c.oid = cls_c.relnamespace
  JOIN pg_class cls_p ON cls_p.oid = c.confrelid
  JOIN pg_namespace ns_p ON ns_p.oid = cls_p.relnamespace AND ns_c.oid = ns_p.oid
WHERE c.contype = 'f' AND ns.nspname <> 'information_schema' AND substr( ns.nspname, 0, 4 ) <> 'pg_'

;

-- unique constraints
SELECT c.oid, c.conname, (SELECT obj_description(c.oid)) AS comment,
                         array_to_string(array(SELECT a.attname FROM pg_attribute a WHERE a.attnum = ANY( c.conkey ) AND a.attrelid = c.conrelid ORDER BY ( 	SELECT i FROM ( SELECT generate_series( array_lower( c.conkey, 1 ), array_upper( c.conkey, 1 ) ) ) g( i ) WHERE c.conkey[i] = a.attnum LIMIT 1 ) ), '
' ) AS unique_fields
FROM pg_constraint c
  JOIN pg_class ON c.conrelid = pg_class.oid
  JOIN pg_namespace n ON n.oid = relnamespace
WHERE c.contype = 'u' AND nspname ='public' AND relname = 'example'

;

-- triggers
SELECT tr.oid, tgtype, tgname AS trigger_name, CONCAT( quote_ident( pr_ns.nspname ), '.',
                                                       quote_ident( proname ) ) AS proname,
                       cl.relname AS event_object,
                       pg_get_triggerdef( tr.oid ) AS text,
                       obj_description( tr.oid ) AS comment
FROM pg_trigger tr
  JOIN pg_class cl ON tr.tgrelid = cl.oid
  JOIN pg_proc pr ON tr.tgfoid = pr.oid
  JOIN pg_namespace pr_ns ON pr.pronamespace = pr_ns.oid
  JOIN pg_namespace ns ON ns.oid = cl.relnamespace
WHERE ns.nspname = 'public' AND cl.relname = 'example' AND NOT tr.tgisinternal

;

-- indices
SELECT ci.relname AS index_name, ct.relname AS table_name, am.amname AS method,
       pg_get_indexdef( i.indexrelid ) as text, i.indexrelid AS id, i.indisunique, i.indisclustered, i.indoption,
       obj_description( i.indexrelid ) AS comment,
       array_to_string( array( SELECT pg_get_indexdef( i.indexrelid, column_number + 1, true ) FROM ( SELECT generate_series( array_lower( i.indkey, 1 ), array_upper( i.indkey, 1 ) ) ) g( column_number ) ORDER BY column_number ) , '
' ) AS column_definitions
FROM pg_index i
  LEFT JOIN pg_class ct ON ct.oid = i.indrelid
  LEFT JOIN pg_class ci ON ci.oid = i.indexrelid
  LEFT JOIN pg_namespace tns ON tns.oid = ct.relnamespace
  LEFT JOIN pg_tablespace ts ON ci.reltablespace = ts.oid
  LEFT JOIN pg_am am ON ci.relam = am.oid
  LEFT JOIN pg_depend dep ON dep.classid = ci.tableoid AND dep.objid = ci.oid AND dep.refobjsubid = '0'
  LEFT JOIN pg_constraint con ON con.tableoid = dep.refclassid AND con.oid = dep.refobjid
WHERE conname IS NULL AND tns.nspname = 'public' AND ct.relname = 'example'

;

-- check constraints
SELECT pg_constraint.oid, conname, obj_description( pg_constraint.oid ) AS comment, consrc
FROM pg_constraint
  JOIN pg_class on pg_constraint.conrelid = pg_class.oid
  JOIN pg_namespace rn ON relnamespace = rn.oid
WHERE contype='c' AND rn.nspname = 'public' AND relname = 'example'

;

-- columns
SELECT att.attname AS column_name, format_type( ty.oid,NULL ) AS data_type,
       ty.oid as type_id, tn.nspname AS type_schema, pg_catalog.pg_get_expr( def.adbin, def.adrelid ) AS column_default,
       NOT att.attnotnull AS is_nullable, att.attnum AS ordinal_position, att.attndims AS dimensions,
       att.atttypmod AS modifiers, col_description( cl.oid, att.attnum ) AS comment,
       CONCAT( '"', cn.nspname, '"."', collname, '"' ) AS collation
FROM pg_attribute att
  JOIN pg_type ty ON ty.oid=atttypid
  JOIN pg_namespace tn ON tn.oid=ty.typnamespace
  LEFT OUTER JOIN pg_collation coll ON att.attcollation = coll.oid
  LEFT OUTER JOIN pg_namespace cn ON coll.collnamespace = cn.oid
  JOIN pg_class cl ON cl.oid=att.attrelid
  JOIN pg_namespace na ON na.oid=cl.relnamespace
  LEFT OUTER JOIN pg_attrdef def ON adrelid = att.attrelid AND adnum = att.attnum
WHERE na.nspname = 'public' AND cl.relname = 'example' AND att.attnum > 0 AND att.attisdropped IS FALSE

;

-- primary key constraints
SELECT pg_class.oid, relname AS tablename, nsp.nspname AS schema, pg_get_userbyid( relowner ) AS owner,conname,
  relhasoids, obj_description( pg_class.oid ) AS comment,
                     ( SELECT COUNT(*) FROM pg_attribute att WHERE att.attrelid = pg_class.oid AND att.attnum > 0 AND att.attisdropped IS FALSE ) AS field_count
FROM pg_class
  JOIN pg_namespace nsp ON relnamespace = nsp.oid
  LEFT OUTER JOIN pg_constraint ON pg_constraint.conrelid = pg_class.oid
WHERE relkind = 'r' AND relname <> 'vs_database_diagrams' AND relname <> 'vs_database_queries' AND nsp.nspname = 'public' AND contype='p'

;

-- types
SELECT t.oid, n.nspname as schema, t.typname as name, obj_description( t.oid ) as comment
FROM pg_type t
  LEFT JOIN   pg_catalog.pg_namespace n ON n.oid = t.typnamespace
WHERE (t.typrelid = 0 OR (SELECT c.relkind = 'c' FROM pg_catalog.pg_class c WHERE c.oid = t.typrelid)) AND
      NOT EXISTS(SELECT 1 FROM pg_catalog.pg_type el WHERE el.oid = t.typelem AND el.typarray = t.oid) AND
      n.nspname NOT IN ('pg_catalog', 'information_schema')

;

-- enum values
SELECT enumlabel
FROM pg_enum
  JOIN pg_type t ON enumtypid = t.oid
  JOIN pg_namespace ns ON t.typnamespace = ns.oid
WHERE ns.nspname = 'public' AND typname = 'assessment_mean_algorithm'

;

-- fetch types
SELECT
  nsp.nspname, t.typtype, t.typname, format_type(t.oid, -1) AS type_sqlname,
                                     obj_description(t.oid, 'pg_type') AS comment, t.typdefault,
  -- domain attributes
  t.typnotnull,
                                     base_type_nsp.nspname AS basetype_nspname, base_type.typname AS basetype_name,
                                     format_type(t.typbasetype, NULLIF(t.typtypmod, -1)) AS basetype_sqlname,
                                     basecoll_nsp.nspname AS basecoll_nspname, basecoll.collname AS basecoll_name
FROM pg_type t
  JOIN pg_namespace nsp ON nsp.oid = t.typnamespace
  LEFT JOIN pg_type base_type ON base_type.oid = t.typbasetype
  LEFT JOIN pg_namespace base_type_nsp ON base_type_nsp.oid = base_type.typnamespace
  LEFT JOIN pg_collation basecoll ON basecoll.oid = t.typcollation
  LEFT JOIN pg_namespace basecoll_nsp ON basecoll_nsp.oid = basecoll.collnamespace
WHERE t.typtype IN ('b','c','d','e','p')
--       AND format_type(t.oid, -1) NOT LIKE '%[]' -- do not fetch array types - they are internal to PostgreSQL
--       AND nsp.nspname = ANY(?)
ORDER BY t.typelem, t.typtype IN ('c', 'd');
-- NOTE: ordering to minimize forward references
-- NOTE: range types are fetched in a separate query - there are quite a lot of specific columns



-- fetch enumeration labels
SELECT nspname, typname, enumlabel
FROM pg_enum
  JOIN pg_type ON pg_type.oid = enumtypid
  JOIN pg_namespace ON pg_namespace.oid = typnamespace
-- WHERE nspname = ANY(?)
ORDER BY enumtypid, enumsortorder;


-- fetch range types
SELECT
  type_nsp.nspname AS type_nspname, type.typname AS type_name,
  format_type(type.oid, -1) AS type_sqlname, obj_description(type.oid, 'pg_type') AS comment,
  subtype_nsp.nspname AS subtype_nspname, subtype.typname AS subtype_name,
  coll_nsp.nspname AS coll_nspname, coll.collname AS coll_name,
  canproc_nsp.nspname AS canproc_nspname, canproc.proname AS canproc_name,
  --   ? getProcArgsSqlExprs("canproc", "cf_argtypes", "cf_argmodes", "cf_argnames", "cf_argdefaults"),
  diffproc_nsp.nspname AS diffproc_nspname, diffproc.proname AS diffproc_name /*,
  ? getProcArgsSqlExprs("diffproc", "df_argtypes", "df_argmodes", "df_argnames", "df_argdefaults") */
FROM pg_range
  JOIN pg_type type ON type.oid = rngtypid
  JOIN pg_namespace type_nsp ON type_nsp.oid = type.typnamespace
  JOIN pg_type subtype ON subtype.oid = rngsubtype
  JOIN pg_namespace subtype_nsp ON subtype_nsp.oid = subtype.typnamespace
  LEFT JOIN pg_collation coll ON coll.oid = rngcollation
  LEFT JOIN pg_namespace coll_nsp ON coll_nsp.oid = coll.collnamespace
  LEFT JOIN pg_proc canproc ON canproc.oid = rngcanonical
  LEFT JOIN pg_namespace canproc_nsp ON canproc_nsp.oid = canproc.pronamespace
  LEFT JOIN pg_proc diffproc ON diffproc.oid = rngsubdiff
  LEFT JOIN pg_namespace diffproc_nsp ON diffproc_nsp.oid = diffproc.pronamespace
WHERE type.typtype = 'r'
--       AND type_nsp.nspname = ANY(?)
;



-- fetch composite type members
SELECT
  rel_nsp.nspname AS rel_nspname, pg_class.relname, pg_class.relkind, attname, attnum,
  col_description(pg_class.oid, attnum) AS comment,
  type_nsp.nspname AS type_nspname, typname,
  format_type(atttypid, NULLIF(atttypmod, -1)) AS type_sqlname,
  coll_nsp.nspname AS coll_nspname, collname,
  pg_get_expr(adbin, adrelid) AS default_expr,
  attnotnull, attislocal,
  seq_class_nsp.nspname AS seq_nspname, seq_class.relname AS seq_name
FROM pg_attribute
  JOIN pg_class ON pg_class.oid = attrelid
  JOIN pg_namespace rel_nsp ON rel_nsp.oid = pg_class.relnamespace
  JOIN pg_type ON pg_type.oid = atttypid
  JOIN pg_namespace type_nsp ON type_nsp.oid = typnamespace
  LEFT JOIN pg_collation ON pg_collation.oid = attcollation
  LEFT JOIN pg_namespace coll_nsp ON coll_nsp.oid = collnamespace
  LEFT JOIN pg_attrdef ON adrelid = pg_class.oid AND adnum = attnum
  LEFT JOIN pg_class seq_class ON seq_class.oid = pg_get_serial_sequence(rel_nsp.nspname || '.' || pg_class.relname, attname)::regclass
  LEFT JOIN pg_namespace seq_class_nsp ON seq_class_nsp.oid = seq_class.relnamespace
WHERE pg_class.relkind = 'c' AND attnum > 0 AND NOT attisdropped
--       AND rel_nsp.nspname = ANY(?)
ORDER BY pg_class.oid, attnum;

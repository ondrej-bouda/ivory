-- Structural queries for PostgreSQL 9.3
-- TODO: incorporate changes and new features from PostgreSQL 9.4 and up

/* NOTE: Queries using some system information functions (e.g., pg_get_function_arguments(oid)) return object names
         qualified relatively to the current search_path. For those objects to be identifiable correctly,
         the search_path shall be set to only contain 'pg_catalog' prior to any querying.
  */

--region Databases

SELECT datname, pg_catalog.shobj_description(oid, 'pg_database') AS comment,
       pg_catalog.pg_encoding_to_char(encoding) AS encoding_name,
       datcollate, datctype
FROM pg_catalog.pg_database;

--endregion

--region Languages

SELECT lanname, pg_catalog.obj_description(oid, 'pg_language') AS comment,
       lanispl AS is_internal
FROM pg_catalog.pg_language;

--endregion

--region Extensions

SELECT extname, extversion, pg_catalog.obj_description(oid, 'pg_extension') AS comment
FROM pg_catalog.pg_extension;

--endregion

--region Event triggers

SELECT evtname, pg_catalog.obj_description(oid, 'pg_event_trigger') AS comment,
       evtevent, evtfoid, evtenabled, evttags
FROM pg_catalog.pg_event_trigger;
-- evtfoid: OID of function which gets called
-- evtenabled: controls in which session_replication_role modes the event trigger fires. O = trigger fires in "origin" and "local" modes, D = trigger is disabled, R = trigger fires in "replica" mode, A = trigger fires always
-- evttags: array of command tags only for which the trigger gets fired

--endregion

--region Schemas

SELECT nspname, pg_catalog.obj_description(oid, 'pg_namespace') AS comment,
       (nspname = 'information_schema' OR substring(nspname for 3) = 'pg_') AS is_system_schema
FROM pg_catalog.pg_namespace;

--endregion

--region Collations

SELECT nspname, collname, pg_catalog.obj_description(pg_collation.oid, 'pg_collation') AS comment,
       collcollate, collctype
FROM pg_catalog.pg_collation
     JOIN pg_catalog.pg_namespace ON pg_namespace.oid = collnamespace;

--endregion

--region Types

-- types other than ranges and arrays
SELECT nsp.nspname, t.typtype, t.typname, pg_catalog.format_type(t.oid, -1) AS type_sqlname,
       pg_catalog.obj_description(t.oid, 'pg_type') AS comment,
       t.typdefault, t.typnotnull,
       base_type_nsp.nspname AS basetype_nspname, base_type.typname AS basetype_name,
       pg_catalog.format_type(t.typbasetype, NULLIF(t.typtypmod, -1)) AS basetype_sqlname,
       subtype_nsp.nspname AS subtype_nspname, subtype.typname AS subtype_name,
       rngcanonical::OID AS canproc_oid,
       rngsubdiff::OID AS diffproc_oid,
       coll_nsp.nspname AS coll_nspname, coll.collname AS coll_name,
       (CASE WHEN t.typtype = 'e'
             THEN (SELECT pg_catalog.array_agg(enumlabel ORDER BY enumsortorder) FROM pg_catalog.pg_enum WHERE enumtypid = t.oid)
             ELSE NULL
        END) AS enum_labels,
       EXISTS(
           SELECT 1
           FROM pg_catalog.pg_depend dep
           WHERE dep.classid = 'pg_type'::regclass AND
                 dep.objid = t.oid AND
                 dep.deptype = 'i'
       ) AS is_internal
FROM pg_catalog.pg_type t
     JOIN pg_catalog.pg_namespace nsp ON nsp.oid = t.typnamespace
     LEFT JOIN (pg_catalog.pg_type base_type
                JOIN pg_catalog.pg_namespace base_type_nsp ON base_type_nsp.oid = base_type.typnamespace
               ) ON base_type.oid = t.typbasetype
     LEFT JOIN (pg_catalog.pg_range
                JOIN pg_catalog.pg_type subtype ON subtype.oid = rngsubtype
                JOIN pg_catalog.pg_namespace subtype_nsp ON subtype_nsp.oid = subtype.typnamespace
               ) ON rngtypid = t.oid
     LEFT JOIN (pg_catalog.pg_collation coll
                JOIN pg_catalog.pg_namespace coll_nsp ON coll_nsp.oid = coll.collnamespace
               ) ON coll.oid = COALESCE(rngcollation, t.typcollation)
WHERE t.typtype IN ('b','c','d','e','p','r') AND
      pg_catalog.format_type(t.oid, -1) NOT LIKE '%[]';
-- relevant for all type types: nspname, typname, type_sqlname, comment
-- base types: typtype = 'b'
-- pseudo types: typtype = 'p'
-- composite types: typtype = 'c' (attributes fetched in a separate query, together with table columns)
-- domains: typtype = 'd', relevant: basetype_*, typnotnull, typdefault, coll_* (constraints fetched in a separate query)
-- enums: typtype = 'e', relevant: enum_labels
-- ranges: typtype = 'r', relevant: subtype_*, canproc_oid, diffproc_oid, coll_*
-- is_internal: whether the type has been created internally by PostgreSQL and is really just a part of internal implementation of another object
-- TODO: range subtype operator class

--endregion

--region Functions

-- functions other than aggregates
SELECT pg_proc.oid, proc_nsp.nspname AS proc_nspname, proname,
       pg_catalog.obj_description(pg_proc.oid, 'pg_proc') AS comment,
       lanname, prosrc, prosecdef, proretset, procost, NULLIF(prorows, 0) AS retrows, proiswindow,
       proleakproof, proisstrict, provolatile,
       pg_catalog.pg_get_function_result(pg_proc.oid) AS rettype_expr,
       pg_catalog.pg_get_functiondef(pg_proc.oid) AS def,
       pg_catalog.pg_get_function_arguments(pg_proc.oid) AS args,
       pg_catalog.pg_get_function_identity_arguments(pg_proc.oid) AS ident_args
FROM pg_catalog.pg_proc
     JOIN pg_catalog.pg_namespace proc_nsp ON proc_nsp.oid = pronamespace
     JOIN pg_catalog.pg_language ON pg_language.oid = prolang
WHERE NOT proisagg;
-- provolatile: i = immutable, s = stable, v = volatile

-- aggregates
SELECT aggproc.oid AS aggproc_oid, aggproc_nsp.nspname AS aggproc_nspname, aggproc.proname AS aggproc_name,
       pg_catalog.obj_description(aggproc.oid, 'pg_proc') AS comment,
       agginitval,
       sttype_nsp.nspname AS sttype_nspname, sttype.typname AS sttype_name,
       format_type(aggtranstype, NULL) AS sttype_sqlname,
       aggtransfn::OID AS transfn_oid, aggfinalfn::OID AS finalfn_oid,
       pg_catalog.pg_get_function_arguments(aggproc.oid) AS args,
       pg_catalog.pg_get_function_identity_arguments(aggproc.oid) AS ident_args
FROM pg_catalog.pg_aggregate
     JOIN pg_catalog.pg_proc aggproc ON aggproc.oid = aggfnoid
     JOIN pg_catalog.pg_namespace aggproc_nsp ON aggproc_nsp.oid = aggproc.pronamespace
     JOIN pg_catalog.pg_type sttype ON sttype.oid = aggtranstype
     JOIN pg_catalog.pg_namespace sttype_nsp ON sttype_nsp.oid = sttype.typnamespace
WHERE aggproc.proisagg;
-- TODO: sort operator
-- TODO: support the SET <identifier> (TO <expression> | FROM CURRENT) clause

-- function arguments
SELECT pg_proc.oid AS proc_oid, tnsp.nspname AS argtype_nspname, t.typname AS argtype_name,
       COALESCE(m, 'i') AS argmode, NULLIF(n, '') AS argname,
       pg_catalog.pg_get_function_arg_default(pg_proc.oid, ord::INT) AS argdefaultexpr
FROM pg_catalog.pg_proc,
     UNNEST(
         COALESCE(proallargtypes, proargtypes),
         proargmodes,
         proargnames
     ) WITH ORDINALITY u(t,m,n,ord)
     JOIN pg_catalog.pg_type t ON t.oid = u.t
     JOIN pg_catalog.pg_namespace tnsp ON tnsp.oid = t.typnamespace
ORDER BY pg_proc.oid, ord;

--endregion

--region Operators

SELECT op.oid, nsp.nspname, op.oprname, pg_catalog.obj_description(op.oid, 'pg_operator') AS comment,
       op.oprkind, op.oprcanmerge, op.oprcanhash,
       left_type.typname AS left_typename, left_nsp.nspname AS left_typenspname,
       right_type.typname AS right_typename, right_nsp.nspname AS right_typenspname,
       res_type.typname AS res_typename, res_nsp.nspname AS res_typenspname,
       op.oprcode::OID AS opfun_oid,
       op.oprjoin::OID AS joinfun_oid,
       op.oprrest::OID AS restfun_oid,
       NULLIF(op.oprcom::OID, 0) AS commutator_oid,
       NULLIF(op.oprnegate::OID, 0) AS negator_oid
FROM pg_catalog.pg_operator op
     JOIN pg_catalog.pg_namespace nsp ON nsp.oid = oprnamespace
     JOIN (pg_catalog.pg_type res_type
           JOIN pg_catalog.pg_namespace res_nsp ON res_nsp.oid = res_type.typnamespace
          ) ON res_type.oid = op.oprresult
     LEFT JOIN (pg_catalog.pg_type left_type
                JOIN pg_catalog.pg_namespace left_nsp ON left_nsp.oid = left_type.typnamespace
               ) ON left_type.oid = op.oprleft
     LEFT JOIN (pg_catalog.pg_type right_type
                JOIN pg_catalog.pg_namespace right_nsp ON right_nsp.oid = right_type.typnamespace
               ) ON right_type.oid = op.oprright
     ;
-- oprkind: b = infix, l = prefix, r = postfix
-- oprcanmerge: whether the operator supports merge joins
-- oprcanhash: whether the operator supports hash joins
-- (left|right)_type*: names of left/right operand types
-- opfun_oid: OID of function implementing the operator
-- joinfun_oid: OID of the join selectivity estimation function
-- restfun_oid: OID of the restriction selectivity estimation function
-- commutator_oid: OID of the commutator operator
-- negator_oid: OID of the negator operator

--endregion

--region Sequences

/* NOTE: Unfortunately, the cache value of a sequence can only be retrieved by SELECTing from the particular sequence,
         which would lead to number of queries linear to the number of sequences.
         Luckily, in case the plpgsql language is available, the sequences may be looped over at the database side.
 */
DO LANGUAGE plpgsql $body$
DECLARE r RECORD;
        cv TEXT;
BEGIN
  CREATE TEMPORARY TABLE sequence_metadata (nspname TEXT, relname TEXT, cache_value TEXT) ON COMMIT DROP;
  FOR r IN SELECT nspname, relname
           FROM pg_catalog.pg_class
                JOIN pg_catalog.pg_namespace ON pg_namespace.oid = relnamespace
           WHERE relkind = 'S'
  LOOP
    EXECUTE format('SELECT cache_value FROM %I.%I', r.nspname, r.relname) INTO cv;
    INSERT INTO sequence_metadata VALUES (r.nspname, r.relname, cv);
  END LOOP;
END
$body$;
SELECT sequence_schema, sequence_name, pg_catalog.obj_description(c.oid, 'pg_class') AS comment,
       start_value, minimum_value, maximum_value, increment, (cycle_option = 'YES') AS cycles,
       cache_value
FROM information_schema.sequences
     JOIN pg_catalog.pg_namespace nsp ON nsp.nspname = sequence_schema
     JOIN pg_catalog.pg_class c ON c.relnamespace = nsp.oid AND c.relname = sequence_name
     JOIN sequence_metadata sm ON sm.nspname = sequence_schema AND sm.relname = sequence_name
WHERE c.relpersistence = 'p';

--endregion

--region Relations

-- tables and views
SELECT nsp.nspname, relname, relkind, pg_catalog.obj_description(cls.oid, 'pg_class') AS comment,
       relpersistence,
       type_nsp.nspname AS type_nspname, typname AS type_name,
       (CASE WHEN cls.relkind IN ('v', 'm') THEN pg_catalog.pg_get_viewdef(cls.oid, TRUE) ELSE NULL END) viewdef,
       (SELECT array_agg(n ORDER BY ord) || array_agg(v ORDER BY ord)
        FROM pg_catalog.pg_options_to_table(cls.reloptions) WITH ORDINALITY AS f(n,v,ord)
       ) AS parameters
FROM pg_catalog.pg_class cls
     JOIN pg_catalog.pg_namespace nsp ON nsp.oid = cls.relnamespace
     LEFT JOIN (pg_catalog.pg_type
                JOIN pg_catalog.pg_namespace type_nsp ON type_nsp.oid = pg_type.typnamespace)
               ON pg_type.oid = reloftype
WHERE relkind IN ('r', 'v', 'm') AND relpersistence IN ('p', 'u');
-- relpersistance: p = permanent, u = unlogged
-- relkind: r = regular table, v = view, m = materialized view
-- parameters: index storage parameters - a list of all the parameter keys followed by the corresponding values

-- columns
SELECT rel_nsp.nspname, pg_class.relname, pg_class.relkind, attnum, attname,
       pg_catalog.col_description(pg_class.oid, attnum) AS comment,
       type_nsp.nspname AS type_nspname, typname AS type_name,
       format_type(atttypid, NULLIF(atttypmod, -1)) AS type_sqlname,
       coll_nsp.nspname AS coll_nspname, collname,
       pg_get_expr(adbin, adrelid) AS default_expr,
       attnotnull, attislocal,
       seq_class_nsp.nspname AS seq_nspname, seq_class.relname AS seq_name,
       (SELECT array_agg(n ORDER BY ord) || array_agg(v ORDER BY ord)
        FROM pg_catalog.pg_options_to_table(attoptions) WITH ORDINALITY AS f(n,v,ord)
       ) AS parameters
FROM pg_catalog.pg_attribute
     JOIN pg_catalog.pg_class ON pg_class.oid = attrelid
     JOIN pg_catalog.pg_namespace rel_nsp ON rel_nsp.oid = pg_class.relnamespace
     JOIN pg_catalog.pg_type ON pg_type.oid = atttypid
     JOIN pg_catalog.pg_namespace type_nsp ON type_nsp.oid = typnamespace
     LEFT JOIN (pg_catalog.pg_collation
                JOIN pg_catalog.pg_namespace coll_nsp ON coll_nsp.oid = collnamespace
               ) ON pg_collation.oid = attcollation
     LEFT JOIN pg_catalog.pg_attrdef ON adrelid = pg_class.oid AND adnum = attnum
     LEFT JOIN (pg_catalog.pg_class seq_class
                JOIN pg_catalog.pg_namespace seq_class_nsp ON seq_class_nsp.oid = seq_class.relnamespace
               ) ON seq_class.oid = pg_catalog.pg_get_serial_sequence(rel_nsp.nspname || '.' || pg_class.relname, attname)::regclass
WHERE pg_class.relkind IN ('r', 'v', 'm', 'c') AND attnum > 0 AND NOT attisdropped
ORDER BY pg_class.oid, attnum;
-- relkind: r = regular table, v = view, m = materialized view, c = composite type
-- attnum: 1-based number of the column (necessary for references from table constraints; a column series on a table might contain gaps)
-- type_*: type of the column
-- coll*: collation used by the column
-- attislocal: whether the column is defined locally (w.r.t. table inheritance)
-- seq*: associated sequence (for the serial column types)
-- parameters: storage parameters of the column (attoptions) - a list of all the parameter keys followed by the corresponding values

-- indexes
SELECT nspname, idx_class.relname AS idx_name, pg_catalog.obj_description(idx_class.oid, 'pg_class') AS comment,
       tbl_class.relkind AS relkind, tbl_class.relname AS tbl_name,
       amname, idx_class.relpersistence, indisunique,
       pg_catalog.pg_get_expr(indpred, indrelid) AS predicate_expr,
       pg_catalog.pg_get_indexdef(idx_class.oid, 0, true) AS def,
       EXISTS(
           SELECT 1
           FROM pg_catalog.pg_depend dep
           WHERE dep.classid = 'pg_class'::regclass AND
                 dep.objid = idx_class.oid AND
                 dep.deptype = 'i'
       ) AS is_internal,
       (SELECT array_agg(pg_catalog.pg_get_indexdef(attrelid, attnum, FALSE) ORDER BY attnum)
        FROM pg_catalog.pg_attribute
        WHERE attrelid = indexrelid
       ) AS part_exprs,
       (SELECT array_agg(n ORDER BY ord) || array_agg(v ORDER BY ord)
        FROM pg_catalog.pg_options_to_table(idx_class.reloptions) WITH ORDINALITY AS f(n,v,ord)
       ) AS parameters
FROM pg_catalog.pg_index
     JOIN pg_catalog.pg_class idx_class ON idx_class.oid = indexrelid
     JOIN pg_catalog.pg_class tbl_class ON tbl_class.oid = indrelid
     JOIN pg_catalog.pg_namespace ON pg_namespace.oid = tbl_class.relnamespace
     JOIN pg_catalog.pg_am ON pg_am.oid = idx_class.relam
WHERE idx_class.relkind = 'i' AND idx_class.relpersistence IN ('p', 'u') AND
      tbl_class.relkind IN ('r', 'v', 'm');
-- tbl_name is always in the same nspname schema
-- relkind: r = regular table, v = view, m = materialized view
-- amname: access method name (btree, gin, gist, ...)
-- relpersistance: p = permanent, u = unlogged
-- indisunique: whether the index holds unique values
-- is_internal: whether the index has been created internally by PostgreSQL and is really just a part of internal implementation of another object
-- parameters: index storage parameters - a list of all the parameter keys followed by the corresponding values

-- index parts
SELECT rel_nsp.nspname, tbl_class.relkind, tbl_class.relname AS tbl_name,
       idx_class.relname AS idx_name, attname, attnum,
       coll_nsp.nspname AS coll_nspname, collname,
       pg_catalog.pg_get_indexdef(attrelid, attnum, FALSE) AS defexpr
FROM pg_catalog.pg_attribute
     JOIN pg_catalog.pg_index ON indexrelid = attrelid
     JOIN pg_catalog.pg_class idx_class ON idx_class.oid = attrelid
     JOIN pg_catalog.pg_class tbl_class ON tbl_class.oid = indrelid
     JOIN pg_catalog.pg_namespace rel_nsp ON rel_nsp.oid = tbl_class.relnamespace
     LEFT JOIN (pg_catalog.pg_collation
                JOIN pg_catalog.pg_namespace coll_nsp ON coll_nsp.oid = collnamespace)
               ON pg_collation.oid = attcollation
WHERE idx_class.relkind = 'i' AND attnum > 0 AND NOT attisdropped
ORDER BY idx_class.oid, attnum;
-- rel_nspname: name of schema the index is defined in
-- relkind: r = regular table, v = view, m = materialized view, i = index, c = composite type
-- tbl_name: name of table/view the index is defined on
-- idx_name: name of the index
-- attname: name of the index part, unique within the index
-- attnum: 1-based number of the index part
-- defexpr: part definition expression
-- TODO: operator class for index
-- TODO: order (DESC)
-- TODO: NULL treatment (NULLS FIRST/LAST) - see http://stackoverflow.com/questions/18121103/how-to-get-the-index-column-orderasc-desc-nulls-first-from-postgresql

-- constraints other than constraint triggers (either a table or a domain may be constrained, not both)
SELECT c.conname, pg_catalog.obj_description(c.oid, 'pg_constraint') AS comment,
       c.contype, c.condeferrable, c.condeferred,
       nsp.nspname, tbl.relname AS tbl_name, dom.typname AS dom_name,
       idx.relname AS idx_name,
       c.conkey,
       (SELECT array_agg(attname ORDER BY position('|' || attnum || '|' in '|' || array_to_string(c.conkey, '|') || '|'))
        FROM pg_catalog.pg_attribute a
        WHERE attrelid = tbl.oid AND
              attnum = ANY(c.conkey)
       ) AS con_col_names,
       c.convalidated, c.conislocal, c.connoinherit,
       reftbl_nsp.nspname AS reftbl_nspname, reftbl.relname AS reftbl_name,
       covcon.conname AS covcon_name,
       c.confupdtype, c.confdeltype, c.confmatchtype,
       c.confkey,
       (SELECT array_agg(attname ORDER BY position('|' || attnum || '|' in '|' || array_to_string(c.confkey, '|') || '|'))
        FROM pg_catalog.pg_attribute a
        WHERE attrelid = reftbl.oid AND
              attnum = ANY(c.confkey)
       ) AS con_col_names,
       pg_catalog.pg_get_expr(c.conbin, COALESCE(tbl.oid, 0)) AS check_expr,
       pg_catalog.pg_get_constraintdef(c.oid, TRUE) AS def
FROM pg_catalog.pg_constraint c
     LEFT JOIN pg_catalog.pg_class tbl ON tbl.oid = c.conrelid
     LEFT JOIN pg_catalog.pg_type dom ON dom.oid = c.contypid
     JOIN pg_catalog.pg_namespace nsp ON nsp.oid = COALESCE(tbl.relnamespace, dom.typnamespace)
     LEFT JOIN pg_catalog.pg_class idx ON idx.oid = c.conindid
     LEFT JOIN (pg_catalog.pg_class reftbl
                JOIN pg_catalog.pg_namespace reftbl_nsp ON reftbl_nsp.oid = reftbl.relnamespace
               ) ON reftbl.oid = c.confrelid
     LEFT JOIN pg_catalog.pg_constraint covcon ON covcon.conindid = c.conindid AND covcon.conrelid = reftbl.oid
WHERE c.contype IN ('c','f','p','u','x')
ORDER BY c.contype IN ('p', 'u') DESC; -- NOTE: ordering to prevent forward references - foreign key constraints may refer unique constraints
-- contype: c = check constraint, f = foreign key constraint, p = primary key constraint, u = unique constraint, x = exclusion constraint
-- nspname: name of schema the constrained table/domain is defined in
-- table constraints:
--   tbl_name: constrained table name
--   conkey: list of constrained columns; each is a column attnum
--   con_col_names: list of constrained column names
--   convalidated: whether the constraint has been validated (e.g., foreign keys or check constraints may be created as NOT VALID)
--   conislocal: whether the constraint is defined locally (w.r.t. table inheritance)
--   connoinherit: whether the constraint is non-inheritable
--   idx_name: name of index backing up the constraint (on the constrained table, or on the referenced table in case of foreign key)
-- domain constraints:
--   dom_name: constrained domain name
-- foreign keys:
--   reftbl*: referenced table
--   confkey: list of referenced columns; each is a column attnum
--   ref_col_names: list of referenced column names
--   covcon_name: covering unique constraint on the referenced table
--   confupdtype, confdeltype: update/delete action: a = no action, r = restrict, c = cascade, n = set null, d = set default
--   confmatchtype: f = full, p = partial, s = simple
-- check constraints:
--   check_expr: the expression to be checked
-- TODO: operators used by foreign keys
-- TODO: operators used by exclusion constraints

-- triggers
SELECT trigger_schema AS nspname, trigger_name, tbl.relkind AS event_object_relkind, event_object_table,
       pg_catalog.obj_description(tr.oid, 'pg_trigger') AS comment,
       pg_catalog.array_agg(
           event_manipulation::TEXT
           ORDER BY (event_manipulation = 'INSERT',
                     event_manipulation = 'UPDATE',
                     event_manipulation = 'DELETE',
                     event_manipulation = 'TRUNCATE'
           ) DESC
       ) AS firing_events,
       action_statement, action_orientation, action_timing, action_condition,
       tgattr::int[] AS update_col_nums,
       (SELECT array_agg(attname ORDER BY attnum)
        FROM pg_catalog.pg_attribute a
        WHERE attrelid = tbl.oid AND
              attnum = ANY(tgattr)
       ) AS update_col_names,
       (tgconstraint != 0) AS is_constraint, tgdeferrable, tginitdeferred,
       pg_catalog.pg_get_triggerdef(tr.oid, true) AS def
FROM information_schema.triggers
     JOIN pg_catalog.pg_namespace tbl_nsp ON tbl_nsp.nspname = event_object_schema
     JOIN pg_catalog.pg_class tbl ON tbl.relnamespace = tbl_nsp.oid AND tbl.relname = event_object_table
     JOIN pg_catalog.pg_trigger tr ON tr.tgrelid = tbl.oid AND tr.tgname = trigger_name
GROUP BY trigger_schema, trigger_name, tbl.oid, tbl.relkind, event_object_table, tr.oid,
         action_statement, action_orientation, action_timing, action_condition,
         tgdeferrable, tginitdeferred, tgattr, tgconstraint;
-- both the trigger and the event object table are in the nspname schema
-- firing_events: a subset of [INSERT, UPDATE, DELETE, TRUNCATE] (in this order)
-- action_orientation: ROW, STATEMENT
-- action_timing: "BEFORE", "AFTER", "INSTEAD OF"
-- action_statement: relations are schema-qualified according to the current search_path; use SET search_path=''
-- update_col_nums: list of 1-based numbers of columns of event_object_table for the ON UPDATE OF ... clause
-- update_col_names: list of names of columns of event_object_table for the ON UPDATE OF ... clause

-- table inheritance
SELECT child_namespace.nspname AS child_nspname, child_class.relname AS child_name,
       parent_namespace.nspname AS parent_nspname, parent_class.relname AS parent_name
FROM pg_catalog.pg_inherits
     JOIN pg_catalog.pg_class child_class ON child_class.oid = inhrelid
     JOIN pg_catalog.pg_namespace child_namespace ON child_namespace.oid = child_class.relnamespace
     JOIN pg_catalog.pg_class parent_class ON parent_class.oid = inhparent
     JOIN pg_catalog.pg_namespace parent_namespace ON parent_namespace.oid = parent_class.relnamespace
ORDER BY parent_class.oid, child_class.oid, inhseqno;

-- rules
SELECT tnsp.nspname, t.relname AS tablename, pg_rewrite.rulename,
                     pg_catalog.obj_description(pg_rewrite.oid, 'pg_rewrite') AS comment,
  ev_type, ev_enabled, is_instead,
  --       pg_catalog.pg_get_expr(ev_qual, ev_class) AS qual_cond,
  --       pg_catalog.pg_get_expr(ev_action, 'pg_rewrite'::regclass) AS action,
  definition
FROM pg_catalog.pg_rewrite
  JOIN pg_catalog.pg_class t ON t.oid = pg_rewrite.ev_class
  JOIN pg_catalog.pg_namespace tnsp ON tnsp.oid = t.relnamespace
  JOIN pg_catalog.pg_rules ON pg_rules.rulename = pg_rewrite.rulename AND
                              pg_rules.schemaname = tnsp.nspname AND
                              pg_rules.tablename = t.relname;
-- TODO: find out what to pass to the pg_get_expr() calls
-- ev_type: 1 = SELECT, 2 = UPDATE, 3 = INSERT, 4 = DELETE
-- ev_enabled: controls in which session_replication_role modes the rule fires. O = rule fires in "origin" and "local" modes, D = rule is disabled, R = rule fires in "replica" mode, A = rule fires always

--endregion

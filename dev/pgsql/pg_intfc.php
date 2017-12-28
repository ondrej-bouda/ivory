<?php
namespace Ivory\Dev\Pgsql;

$conn = pg_connect('dbname=ivory_scratch user=ivory password=ivory');

echo "search_path: ";
$result = pg_query($conn, 'select current_schemas(true)');
var_dump(pg_fetch_result($result, null, 0));

$result = pg_query($conn, "select '1', null, '[2:4][0:1]={{a,1},{b,2},{c,NULL}}'::text[][], 123456.789::money, *, B'101'::varbit(5), substring(B'1101'::varbit(4) for 3) from t");

echo "Types: ";
$fieldCnt = pg_num_fields($result);
for ($i = 0; $i < $fieldCnt; $i++) {
	if ($i != 0) {
		echo ", ";
	}
	echo pg_field_name($result, $i) . ':' . pg_field_type($result, $i);
}
echo "\n";
echo "Errors:\n";
var_dump(pg_field_name(false, 0));
var_dump(pg_field_name($result, $i));
var_dump(pg_field_type(false, 0));
var_dump(pg_field_type($result, $i));
echo "\n";

echo "Fully-qualified types: "; // NOTE: necessary even for the PostgreSQL standard types - pg_catalog may be placed after a user schema in the search path - see http://www.postgresql.org/docs/9.4/static/ddl-schemas.html#DDL-SCHEMAS-CATALOG
$typeOids = [];
for ($i = 0; $i < $fieldCnt; $i++) {
	$typeOids[] = pg_field_type_oid($result, $i);
}
$metaRes = pg_query($conn,
	'SELECT pg_type.oid, typname, nspname
     FROM pg_catalog.pg_type
          JOIN pg_catalog.pg_namespace ON pg_namespace.oid = pg_type.typnamespace
     WHERE pg_type.oid IN (' . implode(',', $typeOids) . ')'
);
$types = [];
while (($row = pg_fetch_assoc($metaRes))) {
	$types[$row['oid']] = $row['nspname'] . '.' . $row['typname'];
}
for ($i = 0; $i < $fieldCnt; $i++) {
	if ($i != 0) {
		echo ", ";
	}
	echo $types[pg_field_type_oid($result, $i)];
}
echo "\n";
echo "Errors:\n";
var_dump(pg_field_type_oid(false, 0));
var_dump(pg_field_type_oid($result, $i));
echo "\n";

echo "Data:\n";
$rowCnt = pg_num_rows($result);
for ($i = 0; $i < $rowCnt; $i++) {
	$row = pg_fetch_row($result, $i);
	var_dump($row);
}



echo "\n\n\n\n";
$query = "WITH a (k,l,m) AS (VALUES (1, 'a', true)) SELECT a FROM a";
echo "$query\n";
$result = pg_query($query);
$fieldCnt = pg_num_fields($result);
$typeOids = [];
for ($i = 0; $i < $fieldCnt; $i++) {
	$typeOids[] = pg_field_type_oid($result, $i);
}
$metaRes = pg_query($conn,
	'SELECT pg_type.oid, typname, nspname
     FROM pg_catalog.pg_type
          JOIN pg_catalog.pg_namespace ON pg_namespace.oid = pg_type.typnamespace
     WHERE pg_type.oid IN (' . implode(',', $typeOids) . ')'
);
$types = [];
while (($row = pg_fetch_assoc($metaRes))) {
	$types[$row['oid']] = $row['nspname'] . '.' . $row['typname'];
}
$fieldCnt = pg_num_fields($result);
for ($i = 0; $i < $fieldCnt; $i++) {
	if ($i != 0) {
		echo ", ";
	}
	echo pg_field_name($result, $i) . ":#{$typeOids[$i]}:{$types[pg_field_type_oid($result, $i)]}";
}
echo "\n";
while (($row = pg_fetch_assoc($result))) {
	print_r($row);
}

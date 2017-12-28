<?php
/**
 * Test how PostgreSQL configuration values may be read, including the
 * {@link http://www.postgresql.org/docs/9.4/static/runtime-config-custom.html customized options}.
 */

namespace Ivory\Dev;

use Ivory\Connection\Config\ConfigParam;

require_once '../src/Ivory/Connection/ConfigParamType.php';
require_once '../src/Ivory/Connection/ConfigParam.php';

$conn = pg_connect('dbname=ivory_scratch user=ivory password=ivory', PGSQL_CONNECT_FORCE_NEW);
if ($conn === false) {
    fprintf(STDERR, "Error connecting to the database\n");
    exit(1);
}


echo 'DateStyle: ';
var_dump(pg_parameter_status($conn, 'DateStyle'));

echo "setting DateStyle to MDY\n";
pg_query($conn, 'SET DateStyle = MDY');

echo 'DateStyle: ';
var_dump(pg_parameter_status($conn, 'DateStyle'));

echo 'datestyle: ';
var_dump(pg_parameter_status($conn, 'datestyle')); // prints "false" - the pg_parameter_status() function is case sensitive :-(


echo "---------------------------------------\n";

foreach (ConfigParam::TYPEMAP as $cp => $type) {
    $v = pg_parameter_status($conn, $cp);
    if ($v !== false) {
        echo "$cp: $v\n";
    }
}

echo "---------------------------------------\n";



echo 'array_nulls using pg_parameter_status(): ';
var_dump(pg_parameter_status('array_nulls'));

echo 'array_nulls using SHOW query: ';
$r = pg_query($conn, 'SHOW array_nulls');
var_dump(pg_fetch_array($r, 0)[0]);

echo 'array_nulls using current_setting() query: ';
$r = pg_query($conn, "SELECT current_setting('array_nulls')");
var_dump(pg_fetch_array($r, 0)[0]);

echo 'array_nulls using pg_settings query: ';
$r = pg_query($conn, "SELECT setting, unit FROM pg_catalog.pg_settings WHERE name = 'array_nulls'");
$row = pg_fetch_array($r, 0);
var_dump($row['setting']);
var_dump($row['unit']);

echo "setting array_nulls = off\n";
pg_query($conn, 'SET array_nulls = off');

echo 'array_nulls using pg_parameter_status(): ';
var_dump(pg_parameter_status('array_nulls'));

echo 'array_nulls using SHOW query: ';
$r = pg_query($conn, 'SHOW array_nulls');
var_dump(pg_fetch_array($r, 0)[0]);

echo 'array_nulls using current_setting() query: ';
$r = pg_query($conn, "SELECT current_setting('array_nulls')");
var_dump(pg_fetch_array($r, 0)[0]);

echo 'array_nulls using pg_settings query: ';
$r = pg_query($conn, "SELECT setting, unit FROM pg_catalog.pg_settings WHERE name = 'array_nulls'");
$row = pg_fetch_array($r, 0);
var_dump($row['setting']);
var_dump($row['unit']);

<?php
/**
 * Test how a pg_num_rows error is communicated.
 */

namespace Ivory\Sandpit;

$conn = pg_connect('dbname=ivory_scratch user=ivory password=ivory', PGSQL_CONNECT_FORCE_NEW);
if ($conn === false) {
    fprintf(STDERR, "Error connecting to the database\n");
    exit(1);
}

pg_query($conn, 'CREATE TEMPORARY TABLE q (id BIGINT)');
pg_query($conn, 'INSERT INTO q VALUES (1)');
pg_query($conn, 'SELECT * FROM q');
$res = pg_query($conn, 'DELETE FROM q');
echo "Now, asking pg_num_rows\n";
$r = pg_num_rows($res);
echo "r:\n";
var_dump($r); // int(0)

echo "---------------------------\n";

$res = pg_query($conn, 'SELECT 1 FROM 2');
echo "Now, asking pg_num_rows\n";
$r = pg_num_rows($res);
echo "r:\n";
var_dump($r); // int(0)

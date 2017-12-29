<?php
/**
 * Test of asynchronous connection closed immediately after starting connecting.
 */
namespace Ivory\Dev\Pgsql;

$connStr = 'dbname=ivory_scratch user=ivory password=ivory';
for ($i = 0; $i < 10000; $i++) {
    $conn = pg_connect($connStr, PGSQL_CONNECT_FORCE_NEW | PGSQL_CONNECT_ASYNC);
    $closed = pg_close($conn);
    if (!$closed) {
        fprintf(STDERR, "pg_close() failed\n");
        exit;
    }
}

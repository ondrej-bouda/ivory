<?php
/**
 * Test of getting transaction status immediately after starting to connect asynchronously.
 */
namespace Ivory\Dev\Pgsql;

$connStr = 'dbname=ivory_scratch user=ivory password=ivory';

for ($i = 0; $i < 100; $i++) {
    $conn = pg_connect($connStr, PGSQL_CONNECT_FORCE_NEW | PGSQL_CONNECT_ASYNC);
    $status = pg_transaction_status($conn); // shall be PGSQL_TRANSACTION_UNKNOWN
    $closed = pg_close($conn);
    if (!$closed) {
        fprintf(STDERR, "pg_close() failed\n");
        exit;
    }

    echo "$status\n";
}

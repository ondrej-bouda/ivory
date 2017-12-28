<?php
/**
 * Test of sending multiple queries in a single pg_query() call.
 *
 * The semantics looks like as follows:
 * - implicit BEGIN is issued before the batch (although, if already intrans, it has no effect)
 * - implicit BEGIN is issued after each explicit COMMIT or ROLLBACK
 * - implicit COMMIT is issued after the batch unless there is an explicit BEGIN in this or any of the previous batches with no following explicit COMMIT or ROLLBACK
 */

namespace Ivory\Dev;

$conn = pg_connect('dbname=ivory_scratch user=ivory password=ivory', PGSQL_CONNECT_FORCE_NEW);
if ($conn === false) {
    fprintf(STDERR, "Error connecting to the database\n");
    exit(1);
}

pg_query("CREATE TEMPORARY TABLE tempt (t TEXT); INSERT INTO tempt (t) VALUES ('');");
pg_query("UPDATE tempt SET t = t || 'a'");
printTxStatus($conn); // idle
pg_query("UPDATE tempt SET t = t || 'b'; BEGIN; UPDATE tempt SET t = t || 'c'; ROLLBACK; UPDATE tempt SET t = t || 'd'"); // BEGIN has no effect - transaction is already started, ROLLBACK cancels the entire batch

printTxStatus($conn); // idle
pg_query('BEGIN');
printTxStatus($conn); // intrans
pg_query("UPDATE tempt SET t = t || 'e'");
printTxStatus($conn); // intrans
pg_query('ROLLBACK');
printTxStatus($conn); // idle
pg_query("UPDATE tempt SET t = t || 'f'");

printTxStatus($conn); // idle
pg_query("UPDATE tempt SET t = t || 'g'; BEGIN; UPDATE tempt SET t = t || 'h'");
printTxStatus($conn); // intrans
pg_query("UPDATE tempt SET t = t || 'i'");
pg_query('ROLLBACK');
printTxStatus($conn); // idle

pg_query("UPDATE tempt SET t = t || 'j'");
printTxStatus($conn); // idle
pg_query("UPDATE tempt SET t = t || 'k'; BEGIN; UPDATE tempt SET t = t || 'l'; ROLLBACK; UPDATE tempt SET t = t || 'm'");
printTxStatus($conn); // idle
pg_query("UPDATE tempt SET t = t || 'n'");
pg_query('ROLLBACK'); // no effect
printTxStatus($conn); // idle

pg_query("UPDATE tempt SET t = t || 'o'");
pg_query("UPDATE tempt SET t = t || 'p'");
pg_query('ROLLBACK'); // no effect

pg_query("UPDATE tempt SET t = t || 'q'");
pg_query("UPDATE tempt SET t = t || 'r'; ROLLBACK; UPDATE tempt SET t = t || 's'; ROLLBACK");

$actual = pg_fetch_result(pg_query('SELECT t FROM tempt'), 0, 0);
echo "$actual\n"; // adfjmnopq
pg_query('DROP TABLE tempt');


pg_query("CREATE TEMPORARY TABLE tempt (t TEXT); INSERT INTO tempt (t) VALUES (''); UPDATE tempt SET t = t || 'a'; ROLLBACK; UPDATE tempt SET t = t || 'b';");




function printTxStatus($conn)
{
    $txStat = pg_transaction_status($conn);
    $txStatuses = [
        PGSQL_TRANSACTION_IDLE => 'idle',
        PGSQL_TRANSACTION_ACTIVE => 'active',
        PGSQL_TRANSACTION_INTRANS => 'intrans',
        PGSQL_TRANSACTION_INERROR => 'inerror',
        PGSQL_TRANSACTION_UNKNOWN => 'unknown',
    ];
    echo "transact status: $txStat ({$txStatuses[$txStat]})\n";
}

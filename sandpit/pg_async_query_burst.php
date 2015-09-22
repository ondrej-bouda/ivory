<?php
/**
 * Test of asynchronous queries, results collected only after sending the whole batch.
 */

namespace Ivory\Sandpit;

$conn = pg_connect('dbname=ivory_scratch user=ivory password=ivory', PGSQL_CONNECT_FORCE_NEW);
if ($conn === false) {
    fprintf(STDERR, "Error connecting to the database\n");
    exit(1);
}

$queries = [
    'START TRANSACTION',
    'CREATE TABLE foo (i int)',
    'SAVEPOINT abc',
    'INSERT INTO foo VALUES (0),(1),(2),(3)',
    'DELETE FROM foo WHERE i > 1',
    'SAVEPOINT def',
    'SELECT *, i % 2 FROM foo WHERE i <= 0',
    'TRUNCATE foo',
    'COMMIT',
    'DROP TABLE foo',
];
$maxI = 10000;
for ($i = 0; $i < $maxI; $i++) {
    echo "Starting batch $i\n";

    if (pg_connection_busy($conn)) {
        fprintf(STDERR, "Connection is busy\n");
        exit(1);
    }

    foreach ($queries as $sql) {
        while (pg_connection_busy($conn));
        $sent = pg_send_query_params($conn, $sql, []);
        if (!$sent) {
            fprintf(STDERR, "Query send failed\n");
            exit(1);
        }
    }

    while (($res = pg_get_result($conn)) !== false) {
        process_result($res);
    }

    echo "---------------------\n";
}

function process_result($res)
{
    $statCodes = [
        PGSQL_EMPTY_QUERY => 'empty query',
        PGSQL_COMMAND_OK => 'command ok',
        PGSQL_TUPLES_OK => 'tuples ok',
        PGSQL_COPY_IN => 'copy in',
        PGSQL_COPY_OUT => 'copy out',
        PGSQL_BAD_RESPONSE => 'bad response',
        PGSQL_NONFATAL_ERROR => 'non-fatal error', // reported as impossible to get this status returned from php pgsql
        PGSQL_FATAL_ERROR => 'fatal error',
    ];
    $statCode = pg_result_status($res);
    $statStr = pg_result_status($res, PGSQL_STATUS_STRING);
    echo "Result status: $statCode ({$statCodes[$statCode]}); $statStr\n";

    switch ($statCode){
        case PGSQL_TUPLES_OK:
            $numRows = pg_num_rows($res);
            $numFields = pg_num_fields($res);
            echo "The result was fetched, having $numRows rows, each having $numFields fields\n";
            break;
        case PGSQL_COMMAND_OK:
            echo "Command OK\n";
            break;
        case PGSQL_COPY_IN:
            echo "COPY IN started\n";
            break;
        case PGSQL_COPY_OUT:
            echo "COPY OUT started\n";
            break;
        default:
            echo "Error occurred.\n";
            $fields = [
                'SQL state' => PGSQL_DIAG_SQLSTATE,
                'Severity' => PGSQL_DIAG_SEVERITY,
                'Message' => PGSQL_DIAG_MESSAGE_PRIMARY,
                'Detail' => PGSQL_DIAG_MESSAGE_DETAIL,
                'Hint' => PGSQL_DIAG_MESSAGE_HINT,
                'Statement position' => PGSQL_DIAG_STATEMENT_POSITION,
                'Internal position' => PGSQL_DIAG_INTERNAL_POSITION,
                'Internal query' => PGSQL_DIAG_INTERNAL_QUERY,
                'Context' => PGSQL_DIAG_CONTEXT,
                'Source file' => PGSQL_DIAG_SOURCE_FILE,
                'Source line' => PGSQL_DIAG_SOURCE_LINE,
                'Source function' => PGSQL_DIAG_SOURCE_FUNCTION,
            ];
            foreach ($fields as $desc => $field) {
                echo "$desc: " . pg_result_error_field($res, $field) . "\n";
            }
            echo "\n";
    }
}

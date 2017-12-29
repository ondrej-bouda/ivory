<?php
/**
 * Test of asynchronous queries, results received immediately after sending each query.
 */
namespace Ivory\Dev\Pgsql;

$conn = pg_connect('dbname=ivory_scratch user=ivory password=ivory', PGSQL_CONNECT_FORCE_NEW);
if ($conn === false) {
    fprintf(STDERR, "Error connecting to the database\n");
    exit(1);
}

if (!pg_connect_poll($conn) === PGSQL_POLLING_OK) {
    fprintf(STDERR, "Connection polling status is not OK\n");
    exit(1);
}

$queries = [
    'SELECT 42',
    'wheee',
    '',
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
$maxI = count($queries);
//$maxI = 10000;
for ($i = 0; $i < $maxI; $i++) {
    $sql = $queries[$i % count($queries)];
    echo "Sending $sql\n";

    if (pg_connection_busy($conn)) {
        fprintf(STDERR, "Connection is busy\n");
        exit(1);
    }

    $sent = pg_send_query($conn, $sql);
    if (!$sent) {
        fprintf(STDERR, "Query send failed\n");
        exit(1);
    }

    $res = pg_get_result($conn);
    if ($res === false) {
        fprintf(STDERR, "No more results are available\n");
        exit(1);
    }

    // For erroneous queries, one must call pg_get_result() once again to update the structures at the client side.
    // Even worse, a loop might actually be needed according to http://www.postgresql.org/message-id/flat/gtitqq$26l3$1@news.hub.org#gtitqq$26l3$1@news.hub.org
    // which does not sound logical, but who knows. Anyway, the following loop should offer the safest solution.
    while (pg_get_result($conn) !== false) {
        trigger_error('The database gave an unexpected result set.', E_USER_NOTICE);
    }

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

    if (!in_array($statCode, [PGSQL_COMMAND_OK, PGSQL_TUPLES_OK, PGSQL_COPY_IN, PGSQL_COPY_OUT])) {
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
    else {
        $numRows = pg_num_rows($res);
        $numFields = pg_num_fields($res);
        echo "The result was fetched, having $numRows rows, each having $numFields fields\n";
    }
    echo "---------------------\n";
}

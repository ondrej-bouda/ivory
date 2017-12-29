<?php
/**
 * Test of processing an error of a multiple queries sent in a single pg_query() call.
 * Besides, test catching notices from successful commands.
 */
namespace Ivory\Dev\Pgsql;

$conn = pg_connect('dbname=ivory_scratch user=ivory password=ivory', PGSQL_CONNECT_FORCE_NEW);
if ($conn === false) {
    fprintf(STDERR, "Error connecting to the database\n");
    exit(1);
}

$sql = 'CREATE OR REPLACE FUNCTION bar() RETURNS VOID LANGUAGE plpgsql AS $$ BEGIN RAISE NOTICE \'Some notice here\'; RAISE NOTICE \'Another notice\'; END; $$;
        CREATE OR REPLACE FUNCTION foo() RETURNS VOID LANGUAGE SQL AS $$ SELECT bar(); $$;
        SELECT foo();
        DROP FUNCTION foo(); DROP FUNCTION bar();
        CREATE TEMPORARY TABLE tt (id SERIAL PRIMARY KEY, i INT UNIQUE);
        INSERT INTO tt (i) VALUES (1), (2), (1);
        SELECT * FROM tt';
$sent = pg_send_query($conn, $sql);
if (!$sent) {
    fprintf(STDERR, "Query send failed\n");
    exit(1);
}
echo "Last notice:\n" . pg_last_notice($conn) . "\n\n";

while (($res = pg_get_result($conn)) !== false) {
    processResult($res);
    echo "Last notice:\n" . pg_last_notice($conn) . "\n\n";
}

echo "=================\n";

pg_send_query($conn, 'SELECT 4');
if (!$sent) {
    fprintf(STDERR, "Query send failed\n");
    exit(1);
}
echo "Last notice:\n" . pg_last_notice($conn) . "\n\n";

while (($res = pg_get_result($conn)) !== false) {
    processResult($res);
    echo "Last notice:\n" . pg_last_notice($conn) . "\n\n";
}



function processResult($res)
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

    echo "Error fields:\n";
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
        echo "$desc: "; var_dump(pg_result_error_field($res, $field));
    }
    echo "\n";
    echo "---------------------\n";
}

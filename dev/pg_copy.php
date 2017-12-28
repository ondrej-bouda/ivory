<?php
/**
 * Test of PostgreSQL COPY FROM/TO facility.
 */

namespace Ivory\Dev;

$conn = pg_connect('dbname=ivory_scratch user=ivory password=ivory', PGSQL_CONNECT_FORCE_NEW);
if ($conn === false) {
    fprintf(STDERR, "Error connecting to the database\n");
    exit(1);
}

$res = pg_query($conn, 'CREATE TEMPORARY TABLE bar (a INT, b char(16), c FLOAT)');
processResult($res);


echo "\n\n=== 1. COPY FROM STDIN ===\n";
/* There are two ways of passing data: as an array of serialized rows, or using a stream, row-by-row.
 * The first option may encapsulate pg_copy_from(); on top it, offer optional check of columns similar to that for
 * pg_copy_to() - see below.
 * The second option may use pg_put_line() for each serialized row, finalized with pg_end_copy().
 */
$res = pg_query($conn, 'COPY bar (a, b, c) FROM STDIN'); // result status: PGSQL_COPY_IN
processResult($res);

pg_put_line($conn, "3\thello world\t4.5\n");
pg_put_line($conn, "4\tgoodbye world\t7.11\n");
pg_put_line($conn, "\\.\n");
pg_end_copy($conn);

$res = pg_query($conn, 'SELECT COUNT(*) FROM bar');
$cnt = pg_fetch_result($res, 0, 0);
echo "bar COUNT(): "; var_dump($cnt); // prints 2


echo "\n\n=== 2. COPY TO STDOUT ===\n";
/* pg_copy_to() is limited to pass the data exactly in the order of the columns definition.
 * It would be nice to optionally accept the intended column order, check it against the actual table definition, and
 * swap the copied data on the fly in case of different order. It would still result in list of rows serialized as
 * strings, just the interface would be richer.
 */
$data = pg_copy_to($conn, 'bar');
var_dump($data);

echo "\n\n=== 2b. COPY TO STDOUT using pg_socket()\n"; // cannot read the actual data
//$stream = pg_socket($conn);
$res = pg_query($conn, 'COPY bar (a, b, c) TO STDOUT'); // result status: PGSQL_COPY_OUT
processResult($res);
var_dump(pg_end_copy($conn));
//while (true) {
//    $read = [$stream];
//    $write = $except = [];
//    $ready = stream_select($read, $write, $except, 4);
//    if ($ready) {
//        echo "ready; reading...\n";
//        $res = socket_read($read[0], 1024);
//        if (strlen($res) == 0) {
//            break;
//        }
//        var_dump($res);
//    }
//    else {
//        echo "Not ready\n";
//    }
//}


echo "\n\n=== 3. COPY TO <file> ===\n";
$filepath = realpath(tempnam(__DIR__, 'cpy'));
$res = pg_query($conn, "COPY bar (a, b, c) TO '$filepath'"); // result status: PGSQL_COMMAND_OK, command tag: COPY 2
processResult($res);


echo "\n\n=== 4. COPY FROM <file> ===\n";
$res = pg_query($conn, "COPY bar (a, b, c) FROM '$filepath'"); // result status: PGSQL_COMMAND_OK, command tag: COPY 2
processResult($res);

$res = pg_query($conn, 'SELECT COUNT(*) FROM bar');
$cnt = pg_fetch_result($res, 0, 0);
echo "bar COUNT(): "; var_dump($cnt); // prints 4


echo "\n\n=== 5. COPY TO PROGRAM <program> ===\n";
$filepath2 = realpath(tempnam(__DIR__, 'cp2'));
$res = pg_query($conn, "COPY bar (a, b, c) TO PROGRAM 'cat > $filepath2'"); // result status: PGSQL_COMMAND_OK, command tag: COPY 4
processResult($res);


echo "\n\n=== 6. COPY FROM PROGRAM <program> ===\n";
$res = pg_query($conn, "COPY bar (a, b, c) FROM PROGRAM 'cat $filepath2'"); // result status: PGSQL_COMMAND_OK, command tag: COPY 4
processResult($res);

$res = pg_query($conn, 'SELECT COUNT(*) FROM bar');
$cnt = pg_fetch_result($res, 0, 0);
echo "bar COUNT(): "; var_dump($cnt); // prints 8




unlink($filepath);
unlink($filepath2);



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
    echo "---------------------\n";
}

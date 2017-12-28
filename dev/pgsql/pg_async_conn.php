<?php
/**
 * Test of asynchronous connections.
 *
 * PHP 5.6+ functions for asynchronous connections and queries are as follows:
 * * pg_socket()
 * * pg_connect_poll()
 * * pg_flush()
 * * pg_consume_input()
 *
 * The only public try anyone has ever made regarding these functions is at https://gist.github.com/rdlowrey/8114597 -
 * that, however, uses the socket as writable, suffers some other problems, and generally does not work. Relying on
 * pg_get_result() blocking until the result is transmitted seems to be the best solution so far.
 *
 * Anyway, the sample code shows asynchronous connecting to the database at least.
 */

namespace Ivory\Dev\Pgsql;

$connStr = 'dbname=ivory_scratch user=ivory password=ivory';
$conn = pg_connect($connStr, PGSQL_CONNECT_FORCE_NEW | PGSQL_CONNECT_ASYNC);
if ($conn === false) {
    fprintf(STDERR, "Error connecting to the database\n");
    exit(1);
}
$status = pg_connection_status($conn);
if ($status === PGSQL_CONNECTION_BAD) {
    fprintf(STDERR, "The connection is bad\n");
    exit(1);
}
elseif ($status === PGSQL_CONNECTION_OK) {
    echo "The connection is OK\n";
}
elseif ($status === PGSQL_CONNECTION_STARTED) {
    echo "The connection is not OK yet, but started\n";
}
else {
    echo 'The connection is in status: ';
    var_dump($status);
}

$stream = pg_socket($conn);
if (!$stream) {
    fprintf(STDERR, "Error getting the socket\n");
    exit(1);
}

$streamReadable = function ($stream) {
    $r = [$stream];
    $w = [];
    $ex = [];
    return (bool)stream_select($r, $w, $ex, 1);
};

while (true) {
    switch (pg_connect_poll($conn)) {
        case PGSQL_POLLING_READING:
            while (!$streamReadable($stream));
            break;
        case PGSQL_POLLING_FAILED:
            fprintf(STDERR, "Error polling the connection\n");
            exit(1);
        case PGSQL_POLLING_OK:
            break 2;
    }
}


for ($i = 0; $i < 10000; $i++) {
    $sql = 'SELECT 1';
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
        fprintf(STDERR, "No results are available\n");
        exit(1);
    }
    if (pg_get_result($conn) !== false) {
        fprintf(STDERR, "More results were obtained\n");
        exit(1);
    }

    $sqlState = pg_result_error_field($res, PGSQL_DIAG_SQLSTATE);
    if ($sqlState !== null) {
        echo "Error occurred.\n";
        echo "SQL STATE: $sqlState\n";
        $fields = [
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
}

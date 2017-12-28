<?php
namespace Ivory\Dev;

$conn = pg_connect('dbname=ivory_scratch user=ivory password=ivory');
pg_query($conn, 'LISTEN ivory');
pg_query($conn, 'LISTEN ivory2');
while (true) {
    var_dump(pg_get_notify($conn, PGSQL_ASSOC));
    sleep(1);
}

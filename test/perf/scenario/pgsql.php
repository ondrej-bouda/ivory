<?php
$args = require __DIR__ . '/common.php';
$connString = $args['connString'];
$testUserCredentials = $args['testUserCredentials'];

$recreate = false;
$dropOnExit = false;


if ($recreate) {
    recreate_database($connString);
}

//region Benchmark
echo "Starting.\n";
echo "Dashboard scenario: plenty of various queries, both summary and listings.\n";
$benchmark = new Benchmark();

for ($round = 1; $round <= $totalRounds; $round++) {
    ob_start(function ($buffer) use ($round) {
        return ($round == 1 ? $buffer : '');
    });

    $benchmark->startSection('0. Connection');

    $conn = pg_connect($connString, PGSQL_CONNECT_FORCE_NEW);
    $res = pg_query('SET search_path = perftest');
    pg_free_result($res);

    $benchmark->endSection();


    $benchmark->startSection('1. User authentication');

    $res = pg_query_params($conn, 'SELECT * FROM usr WHERE lower(email) = lower($1)', [$testUserCredentials['email']]);
    if (!$res) {
        exit('Error authenticating the user');
    }
    $user = pg_fetch_assoc($res);
    pg_free_result($res);
    if ($user['pwd_hash'] !== md5($testUserCredentials['password'])) {
        exit('Invalid password');
    }
    if ($user['is_active'] == 'f') {
        exit('User inactive');
    }
    $res = pg_query_params($conn, 'UPDATE usr SET last_login = CURRENT_TIMESTAMP WHERE id = $1', [$user['id']]);
    pg_free_result($res);
    if ($user['last_login']) {
        echo 'Welcome back since ' . date('n/j/Y H:i:s', strtotime($user['last_login'])) . "\n";
    }

    $benchmark->endSection();


    $benchmark->startSection('2. Starred items');

    $res = pg_query_params($conn,
        'SELECT item.id, item.name, item.description
         FROM usr_starred_item
              JOIN item ON item.id = usr_starred_item.item_id
         WHERE usr_starred_item.usr_id = $1
         ORDER BY usr_starred_item.star_time DESC, item.name',
        [$user['id']]
    );
    $items = [];
    while (($row = pg_fetch_assoc($res))) {
        $items[$row['id']] = $row;
        $items[$row['id']]['categories'] = [];
    }
    pg_free_result($res);
    if ($items) {
        $itemIdList = implode(',', array_keys($items));
        $res = pg_query($conn,
            "SELECT item_id, category_id, name AS category_name
             FROM item_category
                  JOIN category ON category.id = category_id
             WHERE item_id IN ($itemIdList)
             ORDER BY category_name, category_id"
        );
        while (($row = pg_fetch_assoc($res))) {
            $items[$row['item_id']]['categories'][$row['category_id']] = $row['category_name'];
        }
        pg_free_result($res);
    }
    echo "Your starred items:\n";
    foreach ($items as $item) {
        printf('#%d %s', $item['id'], $item['name']);
        if ($item['categories']) {
            $catStrings = [];
            foreach ($item['categories'] as $catId => $catName) {
                $catStrings[] = sprintf('#%d %s', $catId, $catName);
            }
            echo ' (' . implode(', ', $catStrings) . ')';
        }
        echo ': ';
        echo $item['description'];
        echo "\n";
    }
    unset($items);

    $benchmark->endSection();


    $benchmark->startSection('3. Category Items');

    $res = pg_query_params($conn,
        'SELECT item.id, item.name, item.description, item.introduction_date,
                COALESCE(
                  json_object_agg(param.name, item_param_value.value ORDER BY param.priority DESC, param.name)
                    FILTER (WHERE param.id IS NOT NULL),
                  json_build_object()
                ) AS params
         FROM item_category
              JOIN item ON item.id = item_category.item_id
              LEFT JOIN (item_param_value
                         JOIN param ON param.id = item_param_value.param_id
                        ) ON item_param_value.item_id = item.id
         WHERE category_id = $1
         GROUP BY item.id
         ORDER BY item.introduction_date DESC, item.name, item.id',
        [5]
    );
    echo "Category 5:\n";
    while (($row = pg_fetch_assoc($res))) {
        printf('#%d %s, introduced %s: %s',
            $row['id'], $row['name'],
            date('n/j/Y', strtotime($row['introduction_date'])),
            $row['description']
        );
        foreach (json_decode($row['params']) as $parName => $parValue) {
            echo "; $parName: " . strtr(var_export($parValue, true), "\n", ' ');
        }
        echo "\n";
    }
    pg_free_result($res);

    $benchmark->endSection();


    $benchmark->startSection('9. Disconnect');
    pg_close($conn);
    $benchmark->endSection();

    ob_end_flush();
}
//endregion

//region Reporting and epilogue
$benchmark->printReport();

if ($dropOnExit) {
    $conn = pg_connect($connString, PGSQL_CONNECT_FORCE_NEW);
    $res = pg_query($conn, 'DROP SCHEMA perftest CASCADE');
    pg_free_result($res);
    pg_close($conn);
}
//endregion

<?php
use Ivory\Ivory;

require_once '../../bootstrap.php';

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

    $conn = Ivory::setupConnection($connString);
    $conn->connect();
    $conn->getConfig()->setForSession('search_path', 'perftest');

    $benchmark->endSection();


    $benchmark->benchmarkSection('1. First, trivial query', function () use ($conn) {
        $conn->querySingleValue('SELECT 1');
    });


    $benchmark->benchmarkSection('2. User authentication', function () use ($conn, $testUserCredentials, &$user) {
        try {
            $user = $conn->querySingleTuple(
                'SELECT * FROM usr WHERE lower(email) = lower(%s)',
                $testUserCredentials['email']
            );
        }
        catch (Exception $e) {
            exit('Error authenticating the user');
        }
        if ($user['pwd_hash'] !== md5($testUserCredentials['password'])) {
            exit('Invalid password');
        }
        if (!$user['is_active']) {
            exit('User inactive');
        }
        $conn->command('UPDATE usr SET last_login = CURRENT_TIMESTAMP WHERE id = %int', $user['id']);
        if ($user['last_login']) {
            echo 'Welcome back since ' . $user['last_login']->format('n/j/Y H:i:s') . "\n";
        }
    });


    $benchmark->benchmarkSection('3. Starred items', function () use ($conn, $user) {
        $rel = $conn->query(
            'SELECT item.id, item.name, item.description, item.introduction_date,
                    array_agg((category.id, category.name) ORDER BY category.name, category.id) AS categories
             FROM usr_starred_item
                  JOIN item ON item.id = usr_starred_item.item_id
                  LEFT JOIN (item_category
                             JOIN category ON category.id = item_category.category_id
                            ) ON item_category.item_id = item.id
             WHERE usr_starred_item.usr_id = %int
             GROUP BY item.id, usr_starred_item.star_time
             ORDER BY usr_starred_item.star_time DESC, item.name',
            $user['id']
        );
        echo "Your starred items:\n";
        foreach ($rel as $item) {
            printf('#%d %s', $item['id'], $item['name']);
            if ($item['categories']) {
                $catStrings = [];
                foreach ($item['categories'] as $cat) {
                    $catStrings[] = sprintf('#%d %s', $cat['id'], $cat['name']);
                }
                echo ' (' . implode(', ', $catStrings) . ')';
            }
            echo ': ';
            echo $item['description'];
            echo "\n";
        }
    });


    $benchmark->benchmarkSection('4. Category Items', function () use ($conn) {
        $rel = $conn->query(
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
             WHERE category_id = %int
             GROUP BY item.id
             ORDER BY item.introduction_date DESC, item.name, item.id',
            5
        );
        echo "Category 5:\n";
        foreach ($rel as $row) {
            printf('#%d %s, introduced %s: %s',
                $row['id'], $row['name'],
                $row['introduction_date']->format('n/j/Y'),
                $row['description']
            );
            foreach ($row['params']->getValue() as $parName => $parValue) {
                echo "; $parName: " . strtr(var_export($parValue, true), "\n", ' ');
            }
            echo "\n";
        }
    });


    $benchmark->benchmarkSection('9. Disconnect', function () use ($conn) {
        $conn->disconnect();
    });


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

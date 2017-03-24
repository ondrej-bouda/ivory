<?php
require_once __DIR__ . '/../../bootstrap.php';

require_once __DIR__ . '/pgsql.php';
require_once __DIR__ . '/ivory.php';
require_once __DIR__ . '/dibi.php';
require_once __DIR__ . '/doctrine.php';

//region Interface Specification

$command = new Commando\Command();
$command->argument()
    ->require()
    ->describe('Implementation to use')
    ->must(function ($impl) {
        return in_array($impl, ['ivory', 'ivory-sync', 'pgsql', 'dibi', 'dibi-lazy', 'doctrine']);
    })
    ->map(function ($impl) {
        switch ($impl) {
            case 'ivory':
                return new IvoryPerformanceTest();
            case 'ivory-sync':
                return new IvoryPerformanceTest(IvoryPerformanceTest::SYNCHRONOUS);
            case 'pgsql':
                return new PgSQLPerformanceTest();
            case 'dibi':
                return new DibiPerformanceTest();
            case 'dibi-lazy':
                return new DibiPerformanceTest(DibiPerformanceTest::LAZY);
            case 'doctrine':
                return new DoctrinePerformanceTest();
            default:
                throw new RuntimeException('Unsupported implementation requested');
        }
    });

$command->flag('rounds')
    ->describe('Number of rounds to execute')
    ->must(function ($n) { return ($n > 0); })
    ->map(function ($n) { return (int)$n; })
    ->defaultsTo(100);

$command->flag('warmup')
    ->describe('Number of warm-up rounds before actually executing the benchmark rounds.')
    ->must(function ($n) { return ($n >= 0); })
    ->map(function ($n) { return (int)$n; })
    ->defaultsTo(3);

$command->flag('busyloop')
    ->describe(
        'Number of busy-loop iterations to run after connection initialization. This is to demonstrate the effect of ' .
        'asynchronous connecting during other jobs, e.g., loading libraries or processing input. Try, e.g., 100000.'
    )
    ->map(function ($n) { return (int)$n; })
    ->defaultsTo(0);

$command->flag('print-data')
    ->describe('Print the data gathered from the database to stderr.')
    ->boolean();

$command->flag('progress')
    ->describe('Print progress information.')
    ->boolean();

$command->flag('sections')
    ->alias('s')
    ->describe('Comma-separated list of test sections to run. Section 3 implies section 2.')
    ->must(function ($secList) { return preg_match('~^\d+(?:,\d+)*$~', $secList); })
    ->map(function ($secList) { return array_map('intval', explode(',', $secList)); });

$command->flag('recreate')
    ->describe('Recreate the database from scratch before running the benchmark.')
    ->boolean();

$command->flag('drop')
    ->describe('Drop the created schema on exit.')
    ->boolean();

$command->flag('conn')
    ->describe('Connection string to use for connecting.')
    ->defaultsTo(include __DIR__ . '/../conn_params.php');

$command->flag('test-email')
    ->describe('E-mail address to use for the userAuthentication() benchmark section.')
    ->defaultsTo('usr3@example.com');

$command->flag('test-password')
    ->describe('Password to use for the userAuthentication() benchmark section.')
    ->defaultsTo('3');

$command->flag('test-user')
    ->describe('ID of user to use for the starredItems() benchmark section. Defaults to 3.')
    ->map(function ($id) { return (int)$id; })
    ->defaultsTo('3');

$command->flag('test-category')
    ->describe('ID of category to use for the categoryItems() benchmark section. Defaults to 5.')
    ->map(function ($id) { return (int)$id; })
    ->defaultsTo('5');

//endregion

//region Init

if ($command['recreate']) {
    recreate_database($command['conn'], (bool)$command['progress']);
}

$benchmark = new Benchmark();
/** @var IPerformanceTest $impl */
$impl = $command[0];

//endregion

//region Run the benchmark

$printData = $command['print-data'];
for ($round = 1 - $command['warmup']; $round <= $command['rounds']; $round++) {
    $benchmark->setWarmUp(($round < 1));
    
    ob_start();

    $benchmark->startSection('0. Connection');
    $impl->connect($command['conn'], 'perftest');
    $benchmark->busyLoop($command['busyloop']);
    $benchmark->endSection();

    if (!$command['sections'] || in_array(1, $command['sections'])) {
        $benchmark->startSection('1. First, trivial query');
        $impl->trivialQuery();
        $benchmark->endSection();
    }

    if (!$command['sections'] || in_array(2, $command['sections'])) {
        $benchmark->startSection('2. User authentication');
        $impl->userAuthentication($command['test-email'], $command['test-password']);
        $benchmark->endSection();
    }

    if (!$command['sections'] || in_array(3, $command['sections'])) {
        $benchmark->startSection('3. Starred items');
        $impl->starredItems($command['test-user']);
        $benchmark->endSection();
    }

    if (!$command['sections'] || in_array(4, $command['sections'])) {
        $benchmark->startSection('4. Category Items');
        $impl->categoryItems($command['test-category']);
        $benchmark->endSection();
    }

    $benchmark->startSection('X. Disconnect');
    $impl->disconnect();
    $benchmark->endSection();

    if ($printData) {
        $buffer = ob_get_clean();
        fprintf(STDERR, "%s", $buffer);
        $printData = false;
    }
    else {
        ob_end_clean();
    }
}

//endregion

//region Reporting and epilogue

$benchmark->printReport();

if ($command['drop']) {
    if ($command['progress']) {
        echo "Dropping perftest schema...\n";
    }
    $conn = pg_connect($command['conn'], PGSQL_CONNECT_FORCE_NEW);
    $res = pg_query($conn, 'DROP SCHEMA perftest CASCADE');
    pg_free_result($res);
    if ($command['progress']) {
        echo "Done.\n";
    }
    pg_close($conn);
}

//endregion


//region Classes and functions

interface IPerformanceTest
{
    public function connect(string $connString, string $searchPathSchema);

    public function trivialQuery();

    public function userAuthentication(string $email, string $password): int;

    public function starredItems(int $userId);

    public function categoryItems(int $categoryId);

    public function disconnect();
}


function recreate_database(string $connString, bool $printProgress = false)
{
    $perfSchemaSql = file_get_contents(__DIR__ . '/../perfdb.sql');

    $conn = pg_connect($connString, PGSQL_CONNECT_FORCE_NEW);

    $res = pg_query($conn, 'DROP SCHEMA IF EXISTS perftest CASCADE');
    pg_free_result($res);
    if ($printProgress) {
        echo "Creating perftest schema...\n";
    }
    $res = pg_query($conn, $perfSchemaSql);
    pg_free_result($res);
    if ($printProgress) {
        echo "Schema created and filled with data.\n";
    }
    $res = pg_query($conn, 'ANALYZE');
    pg_free_result($res);
    if ($printProgress) {
        echo "Analyzed.\n";
    }
    pg_close($conn);
}


class Benchmark
{
    /** @var float[][] map: section label => list: lap time */
    private $measurements = [];
    private $curSection;
    private $startTime;
    private $warmUp = false;

    public function benchmarkSection(string $label, \Closure $body)
    {
        $this->startSection($label);
        $body();
        $this->endSection();
    }

    public function startSection(string $label)
    {
        $this->curSection = $label;
        $this->startTime = microtime(true);
    }

    public function endSection()
    {
        $endTime = microtime(true);
        $lapTime = $endTime - $this->startTime;

        if (!$this->warmUp) {
            $list =& $this->measurements[$this->curSection];
            if (!isset($list)) {
                $list = [];
            }
            $list[] = $lapTime;
        }

        $this->startTime = null;
    }

    public function printReport()
    {
        echo str_repeat('-', 80) . "\n";
        $avgSum = 0;
        foreach ($this->measurements as $label => $lapTimeList) {
            $count = count($lapTimeList);
            $avg = array_sum($lapTimeList) / $count;
            printf("%4dx %-40s %f\n", $count, "$label:", $avg);
            $avgSum += $avg;
        }
        printf("Total:             %f\n", $avgSum);
        printf("Peak memory usage: %d kB\n", round(memory_get_peak_usage() / 1024));
    }

    public function busyLoop(int $rounds)
    {
        $num = 0;
        for ($i = 1; $i < $rounds; $i++) {
            $num *= $i;
        }
    }

    public function setWarmUp(bool $warmUp)
    {
        $this->warmUp = $warmUp;
    }
}

//endregion

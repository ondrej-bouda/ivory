<?php
$connString = require __DIR__ . '/../conn_params.php';
$testUserCredentials = ['email' => 'usr3@example.com', 'password' => '3'];
$totalRounds = 100;

return compact('connString', 'testUserCredentials', 'totalRounds');


function recreate_database(string $connString)
{
    $perfSchemaSql = file_get_contents(__DIR__ . '/../perfdb.sql');

    $conn = pg_connect($connString, PGSQL_CONNECT_FORCE_NEW);

    $res = pg_query($conn, 'DROP SCHEMA IF EXISTS perftest CASCADE');
    pg_free_result($res);
    echo "Creating perftest schema...\n";
    $res = pg_query($conn, $perfSchemaSql);
    pg_free_result($res);
    echo "Schema created and filled with data.\n";
    $res = pg_query($conn, 'ANALYZE');
    pg_free_result($res);
    echo "Analyzed.\n";
    pg_close($conn);
}


class Benchmark
{
    /** @var float[][] map: section label => list: lap time */
    private $measurements = [];
    private $curSection;
    private $startTime;

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

        $list =& $this->measurements[$this->curSection];
        if (!isset($list)) {
            $list = [];
        }
        $list[] = $lapTime;

        $this->startTime = null;
    }

    public function printReport()
    {
        echo str_repeat('-', 80) . "\n";
        foreach ($this->measurements as $label => $lapTimeList) {
            $count = count($lapTimeList);
            $avg = array_sum($lapTimeList) / $count;
            printf("%4dx %-40s %f\n", $count, "$label:", $avg);
        }
        echo "Peak memory usage: " . round(memory_get_peak_usage() / 1024) . " kB\n";
    }
}


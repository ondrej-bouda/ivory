<?php
use Ivory\Exception\StatementException;
use Ivory\Ivory;

require __DIR__ . '/../test/bootstrap.php';

$connString = include __DIR__ . '/../test/conn_params.php';
$conn = Ivory::setupConnection($connString);
$conn->command('START TRANSACTION');
$conn->command('CREATE TABLE tt ()');
$conn->command('SAVEPOINT s1');
try {
    $conn->query('SELECT 1 FROM x');
} catch (StatementException $e) {
    $conn->command('ROLLBACK TO SAVEPOINT s1');
}
echo $conn->querySingleValue('SELECT 1') . "\n"; // recovered again, thanks to the savepoint
$conn->command('COMMIT');

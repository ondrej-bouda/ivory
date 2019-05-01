<?php
declare(strict_types=1);

use Ivory\Ivory;
use Ivory\Showcase\IPCTest;

require_once __DIR__ . '/../../../bootstrap.php';

$connString = include __DIR__ . '/../../../conn_params.php';

$conn = Ivory::setupNewConnection($connString);
$conn->connect();

IPCTest::notifierProcess($conn);

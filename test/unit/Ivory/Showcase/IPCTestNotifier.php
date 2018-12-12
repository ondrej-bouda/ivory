<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../bootstrap.php';

$connString = include __DIR__ . '/../../../conn_params.php';

$conn = \Ivory\Ivory::setupNewConnection($connString);
$conn->connect();

\Ivory\Showcase\IPCTest::notifierProcess($conn);

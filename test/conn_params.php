<?php

use Ivory\Connection\ConnectionParameters;

$connStringRetriever = function () {
    $configFile = realpath(__DIR__ . '/phpunit.xml');
    if ($configFile === false) {
        throw new RuntimeException('Config file not found');
    }
    $doc = new DOMDocument();
    $doc->load($configFile);

    $phpUnitElmtList = $doc->getElementsByTagName('phpunit');
    assert($phpUnitElmtList->length == 1);

    $phpElmtList = $phpUnitElmtList->item(0)->getElementsByTagName('php');
    assert($phpElmtList->length == 1);

    $varElmtList = $phpElmtList->item(0)->getElementsByTagName('var');
    $connStrItems = [];
    $itemMap = [
        'DB_HOST' => ConnectionParameters::HOST,
        'DB_PORT' => ConnectionParameters::PORT,
        'DB_USER' => ConnectionParameters::USER,
        'DB_PASSWD' => ConnectionParameters::PASSWORD,
        'DB_DBNAME' => ConnectionParameters::DBNAME,
    ];
    for ($i = 0; $i < $varElmtList->length; $i++) {
        $varElmt = $varElmtList->item($i);
        $key = $varElmt->getAttribute('name');
        $val = $varElmt->getAttribute('value');

        if (strlen($val) > 0 && isset($itemMap[$key])) {
            $connStrItems[] = $itemMap[$key] . '=' . $val;
        }
    }

    return implode(' ', $connStrItems);
};
return $connStringRetriever();

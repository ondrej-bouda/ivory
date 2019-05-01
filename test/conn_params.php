<?php
declare(strict_types=1);

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

    $phpUnitElmt = $phpUnitElmtList->item(0);
    assert($phpUnitElmt instanceof DOMElement);
    $phpElmtList = $phpUnitElmt->getElementsByTagName('php');
    assert($phpElmtList->length == 1);

    $phpElmt = $phpElmtList->item(0);
    assert($phpElmt instanceof DOMElement);
    $varElmtList = $phpElmt->getElementsByTagName('var');
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
        assert($varElmt instanceof DOMElement);
        $key = $varElmt->getAttribute('name');
        $val = $varElmt->getAttribute('value');

        if (strlen($val) > 0 && isset($itemMap[$key])) {
            $connStrItems[] = $itemMap[$key] . '=' . $val;
        }
    }

    return implode(' ', $connStrItems);
};
return $connStringRetriever();

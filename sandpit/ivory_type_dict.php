<?php
use Ivory\Ivory;
use Ivory\Type\ICacheableTypeDictionary;

require_once __DIR__ . '/../test/bootstrap.php';

$connString = include __DIR__ . '/../test/conn_params.php';

$conn = Ivory::setupConnection($connString);
$conn->connect();
$res = $conn->querySingleValue('SELECT 1'); // just to force Ivory initialize the type dictionary
$dict = $conn->getTypeDictionary();
assert($dict instanceof ICacheableTypeDictionary);

$dict->detachFromConnection();
$dict->setUndefinedTypeHandler(null);

$serialized = serialize($dict);
/** @var ICacheableTypeDictionary $unserialized */
$unserialized = unserialize($serialized);

echo 'TypeDictionary serialized size: ' . strlen($serialized) . PHP_EOL;
return;
echo 'Equality test ' . ($unserialized == $dict ? 'passed' : 'failed') . PHP_EOL;
echo 'Orig reference test ' . ($dict->requireTypeByName('s') === $dict->requireTypeByName('text', 'pg_catalog')) . PHP_EOL;
echo 'Reference test ' . ($unserialized->requireTypeByName('s') === $unserialized->requireTypeByName('text', 'pg_catalog')) . PHP_EOL;

echo 'SPL same object test: ' . (spl_object_hash($unserialized->requireTypeByName('s')) === spl_object_hash($unserialized->requireTypeByName('text', 'pg_catalog'))) . PHP_EOL;

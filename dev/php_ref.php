<?php
// Handling of forward references to array items.
// Necessary for TypeDictionary::$typeAliases.

$array = [];
$array['a'] = [];
$ref =& $array['a']['b'];

$array['a']['b'] = 4;

var_dump($ref);
//unset($array['a']);
//var_dump($ref);

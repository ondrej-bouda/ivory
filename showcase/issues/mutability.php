<?php
/**
 * Decide whether methods called on relations shall modify them directly, or rather create a new relation configured appropriately.
 */
namespace Ppg\Showcase\Issues;

use Ppg\Data\DbTableRelation;


$personRel = new DbTableRelation('person');
$projected = $personRel->project(['id', 'firstname', 'lastname']);


// VERSION 1: mutable relations

foreach ($personRel as $row) {
	print_r($row); // prints just the 'id', 'firstname', and 'lastname' attributes
}


// VERSION 2: immutable relations

foreach ($personRel as $row) {
	print_r($row); // prints the whole person row, not just the three attributes
}

// VERSION 2a: the project() and similar methods copy the entire object into a new one

// VERSION 2b: the project() and similar methods create a RelationConfiguration instance, which holds the configuration
//             in a mutable way but refers to the original, untouched relation using a pointer

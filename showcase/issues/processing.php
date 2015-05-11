<?php
/**
 * Processing the results in a structured way.
 */
namespace Ppg\Showcase\Issues;

use Ppg\Data\DbViewRelation;
use Ppg\Data\StatementRelation;

$vwRel = new DbViewRelation('vw_lesson_available_person');

$rel = new StatementRelation(
	"SELECT lesson_id, person_id, person.lastname, person.firstname
	 FROM %
	      JOIN person ON person.id = person_id
	 WHERE lesson_id IN %ld AND person_id IN %ld
	 ORDER BY lesson_id, person.lastname, person.firstname",
	$vwRel,
	[1, 3, 4],
	['8', '23', '4'] // never mind these are strings - they get converted to a list of ints due to "%ld"
);


// VERSION 1
// +: flexible, explicit
// -: readability

$map = $rel->map('lesson_id', $rel->map('person_id', function ($row) {
	return Person::getScheduleIdentifier($row);
}));



// VERSION 2
// +: resembles the resulting data map
// -: does not allow a closure to be used for constructing the map keys

$map = $rel->fetch(['lesson_id' => ['person_id' => function ($row) {
	return Person::getScheduleIdentifier($row);
}]]);



// VERSION 3

$map = $rel->assoc('lesson_id,person_id', function ($row) {
	return Person::getScheduleIdentifier($row);
});



// VERSION 4
// +: clear, readable
// -: limited, cannot combine structures of multiple types, e.g., map of lists of maps; maybe using some special instructions within the arguments?

$map = $rel->assoc('lesson_id', 'person_id', function ($row) {
	return Person::getScheduleIdentifier($row);
});

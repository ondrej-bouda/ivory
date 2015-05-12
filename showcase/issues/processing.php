<?php
/**
 * Processing the results to a structure.
 */
namespace Ppg\Showcase\Issues;

use Ppg\Data\DbViewRelation;
use Ppg\Data\StatementRelation;

$vwRel = new DbViewRelation('vw_lesson_available_person');

/*
 * The following statement queries the database for lessons and persons (teachers) assigned to them.
 * There are to states of lesson-person relation:
 * - "scheduled" means which teachers regularly teach in the lesson, i.e., reflect the normal state,
 * - "actual" means which teachers will actually be teaching the lesson; those may be different from the scheduled ones
 *   in case of supply teaching.
 * Hence the lessonperson.schedulingstatus attribute, holding enum values "scheduled" or "actual".
 */
$rel = new StatementRelation(
	'SELECT lesson.id AS lesson_id, lesson.name AS lesson_name,
            lp.schedulingstatus,
			p.id AS person_id, p.firstname AS person_firstname, p.lastname AS person_lastname, p.schedabbr AS person_schedabbr
	 FROM lesson
	      JOIN lessonperson lp ON lp.lesson_id = lesson.id
	      JOIN person p ON p.id = lp.person_id
	 WHERE lesson.actual_timerange && %',
	new DateTimeRange(new \DateTime(), new \DateTime('+1 day'))
);

// CASE 1; map: lesson ID => row
$res = $rel->map('lesson_id');
var_dump($res[4]['lesson_name']); // prints, e.g., "Geometry", which is the name of lesson 4
var_dump($res[4]['person_lastname']); // prints, e.g., "Brezina", which is the lastname of the last teacher returned for lesson 4

// CASE 2; map: lesson ID => map: person ID => person lastname
$res = $rel->map('lesson_id')->map('person_id')->project('person_lastname');
var_dump($res[4][7]); // prints, e.g., "Brezina", which is the lastname of person 7, who teaches in lesson 4

// CASE 3; map: lesson ID => map: person ID => row only with the scheduling status and person attributes
$res = $rel->map('lesson_id')->map('person_id')->project(['schedulingstatus', 'person_firstname', 'person_lastname', 'person_schedabbr']);
var_dump($res[4][7]['person_lastname']); // prints, e.g., "Brezina", which is the lastname of person 7, who teaches in lesson 4

// CASE 4; map: lesson ID => map: person ID => user function result
$res = $rel->map('lesson_id')->map('person_id')->project(function ($row) {
	return ($row['person_schedabbr'] ? : mb_substr($row['person_lastname'], 0, 4));
});
var_dump($res[4][7]); // prints, e.g., "Brez", which is the scheduling abbreviation of person 7, who teaches in lesson 4

// CASE 5; map: lesson ID => list: row
$res = $rel->map('lesson_id')->list();
var_dump($res[4][2]['person_lastname']); // prints, e.g., "Novak", which is the lastname of the third teacher returned for lesson 4

// CASE 6; map: lesson ID => list: person lastname
$res = $rel->map('lesson_id')->list()->project('person_lastname');
var_dump($res[4][2]); // prints, e.g., "Novak", which is the lastname of the third teacher returned for lesson 4

// CASE 7; map: lesson ID => map: scheduling status => map: person ID => person lastname
$res = $rel->map('lesson_id')->map('schedulingstatus')->map('person_id')->project('person_lastname');
var_dump($res[4]['actual'][7]); // prints, e.g., "Brezina", which is the lastname of person 7, who is the actual teacher of lesson 4

// CASE 8; map: lesson ID => map: scheduling status => list: person ID
$res = $rel->map('lesson_id')->map('schedulingstatus')->list()->project('person_id');
var_dump($res[4]['actual'][1]); // prints, e.g., 89, which is the ID of the second actual teacher returned for lesson 4

// CASE 9; map: lesson ID parity => list: row only with the lesson ID and person ID
$res = $rel->map(function ($row) { return $row['lesson_id'] % 2; })->list()->project(['lesson_id', 'person_id']);
var_dump($res[1][5]['lesson_id']); // prints, e.g., 631, which is the ID of the sixth returned lesson with odd ID

// CASE 10; map: lesson ID => list of rows with the "actual" scheduling status: person ID
$res = $rel->map('lesson_id')->list()->filter(['schedulingstatus' => 'actual'])->project('person_id');
var_dump($res[4][1]); // prints, e.g., 89, which is the ID of the second actual teacher returned for lesson 4

// CASE 11; map: lesson ID => list of rows with odd person ID: person ID
$res = $rel->map('lesson_id')->list()->filter(function ($row) { return $row['person_id'] % 2 == 1; })->project('person_id');
var_dump($res[4][1]); // prints, e.g., 97, which is the ID of the second teacher returned for lesson 4 who has their person ID odd

// CASE 12; equivalent to CASE 11, but filtering sooner, yet on the application side
$res = $rel->filter(function ($row) { return $row['person_id'] % 2 == 1; })->map('lesson_id')->list()->project('person_id');

// CASE 13; list: row only with the lesson_id and person_id attributes
$res = $rel->project(['lesson_id', 'person_id']);
var_dump($res[3]['lesson_id']); // prints, e.g., 142, which is the lesson ID of the fourth returned lesson-person row

// note all the above cases are called on a single $rel object; this should be possible - the relation shall create
//   a new result object with each map()/filter()/project() call



// SHORTCUT PROPOSITIONS

// shortcut for CASE 2; consecutive operands of the same processing operation merged into a single operation call
$res = $rel->map('lesson_id', 'person_id')->project('person_lastname');

// shortcut for CASE 3; projecting all columns with a given prefix using a star
$res = $rel->map('lesson_id')->map('person_id')->project(['schedulingstatus', 'person_*']);

// shortcut for CASE 7; combination of mapping and projection
$res = $rel->map('lesson_id')->map('schedulingstatus')->assoc('person_id', 'person_lastname');






$rel = new StatementRelation(
	'SELECT lesson_id, person_id, person.lastname, person.firstname
	 FROM %
	      JOIN person ON person.id = person_id
	 WHERE lesson_id IN %ld AND person_id IN %ld
	 ORDER BY lesson_id, person.lastname, person.firstname',
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
// -: might be limited - how to combine structures of multiple types, e.g., map of lists of maps?; maybe using some special instructions within the arguments?

$map = $rel->assoc('lesson_id', 'person_id', function ($row) {
	return Person::getScheduleIdentifier($row);
});


<?php
namespace Ppg\Showcase;

use Ppg\Data\DbTableRelation;


$personRel = new DbTableRelation('person');

$joesRel = $personRel->findWhere('firstname = %', 'Joe'); // the percent sign for no conversion - take "string"

foreach ($joesRel as $row) {
	printf("%s %s\n", $row['firstname'], $row['lastname']);
}

$joesName = $joesRel->project(['firstname', 'lastname', 'dateofbirth']); // project() limits the attributes to be fetched only to the listed ones
foreach ($joesName as $i => $row) {
	printf("%d: %s %s, born on %s\n",
		$i, $row['firstname'], $row['lastname'], $row['dateofbirth']->format('Y-m-d')
	);
}

$joesLastnameMap = $joesRel->map('id', 'lastname');
foreach ($joesLastnameMap as $id => $lastname) {
	printf("%d: %s\n", $id, $lastname);
}

$joesRowMap = $joesRel->map('id', ['firstname', 'lastname', 'dateofbirth']);
foreach ($joesRowMap as $id => $row) {
	printf("%d: %s %s, born on %s\n",
		$id, $row['firstname'], $row['lastname'], $row['dateofbirth']->format('Y-m-d')
	);
}

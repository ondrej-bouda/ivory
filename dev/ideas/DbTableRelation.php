<?php
namespace Ivory\Dev\Ideas;

$personRel = new DbTableRelation('person');

$joesRel = $personRel->findWhere('firstname = %', 'Joe'); // the percent sign for no conversion - take "string"

foreach ($joesRel as $row) {
    printf("%s %s\n", $row['firstname'], $row['lastname']);
}

// project() limits the attributes to be fetched only to the listed ones
$joesName = $joesRel->project(['firstname', 'lastname', 'dateofbirth']);
foreach ($joesName as $i => $row) {
    printf("%d: %s %s, born on %s\n",
        $i, $row['firstname'], $row['lastname'], $row['dateofbirth']->format('Y-m-d')
    );
}

$joeRows = $joesRel->map('id'); // fetch all columns, map by id
foreach ($joeRows as $id => $row) {
    printf("person #%d:\n", $id);
    foreach ($row as $field => $value) {
        printf("  %s => %s\n", $field, $value);
    }
}

$joesLastnameMap = $joesRel->map('id', 'lastname'); // fetch a map of person ids to lastnames
foreach ($joesLastnameMap as $id => $lastname) {
    printf("%d: %s\n", $id, $lastname);
}

// fetch a map of person ids to rows containing the person firstname, lastname, and date of birth
$joesRowMap = $joesRel->map('id', ['firstname', 'lastname', 'dateofbirth']);
foreach ($joesRowMap as $id => $row) {
    printf("%d: %s %s, born on %s\n",
        $id, $row['firstname'], $row['lastname'], $row['dateofbirth']->format('Y-m-d')
    );
}

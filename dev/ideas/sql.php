<?php
namespace Ivory\Dev\Ideas;

use Ivory\Data\DbTableRelation;
use Ivory\Data\DbViewRelation;
use Ivory\Data\StatementRelation;

$personRel = new DbTableRelation('person');
$availPersonRel = new DbViewRelation('vw_avail_person', 'datatemplate');

$employeeIds = [1, 432, 92, 32];
$roomIds = [3, 4, 5];

$sql = new StatementRelation(
    "SELECT avail.room_id, %ln@p, 'the % sign is not expanded inside string literals'
	 FROM % avail
	      JOIN % p ON p.id = avail.person_id
	 WHERE avail.room_id IN (%l) AND p.id IN (%l)
	 ORDER BY avail.room_id, p.lastname, p.firstname",
    $personRel->project('id', 'firstname', 'lastname'), // limit the person relation only to these attributes
    $availPersonRel, // the real power comes in: any relation shall be applicable in the query
    $personRel,
    $roomIds, $employeeIds
);
// "%l" generally means a list of something; another formatting token may follow, specifying the list subtype.
// The list subtype may be omitted to use the identity conversion, just like for any argument.
// Here, "%ln" denotes a list of identifiers; in this case, it is fed with a relation, which causes to take all its
// (three) attributes.
// The "@" sign denotes aliasing the relation of attributes.
foreach ($sql as $row) {
    printf("%s %s available for room #%d\n", $row['firstname'], $row['lastname'], $row['room_id']);
}


class PersonRole extends DbTableRelation
{
    public function __construct()
    {
        parent::__construct('person_role');
    }
}

class Role extends DbTableRelation
{
    const ADMIN = 1;

    public function __construct()
    {
        parent::__construct('role');
    }
}

$adminInfo = $personRel->project([
    'id',
    'fname' => 'firstname',
    'lastname' => 'UPPER(lastname)',
    // Unfortunately, PHP implements ::class as mere string, not an object of a special class, thus, the explicit %n
    // must be used.
    'is_admin' => new Statement("EXISTS(SELECT 1 FROM %n WHERE role = %)", PersonRole::class, Role::ADMIN),
]);
foreach ($adminInfo as $row) {
    echo $row['fname'] . ' ' . $row['lastname'];
    if ($row['is_admin']) {
        echo ' is admin';
    }
    echo "\n";
}

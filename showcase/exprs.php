<?php
namespace Ivory\Showcase;

use Ivory\Data\StatementRelation;
use Ivory\Data\ValuesRelation;

$valRel = new ValuesRelation([
	[1, 'a', 3.14, true],
	[5, 'g', 2.81, false],
]);
$sel = new StatementRelation(
	"SELECT * FROM % AS vals (num, letter, decim, flag) WHERE flag",
	$valRel
);
foreach ($sel as $row) {
	printf("%d, %s, %f\n", $row['num'], $row['letter'], $row['decim']);
}

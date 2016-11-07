<?php
/**
 * @deprecated relation mapping was redesigned
 * @todo remove this showcase script
 */

use Ivory\Data\Map\IRelationMap;
use Ivory\Relation\IRelation;

/** @var IRelation $rel */
$rel = null;
/** @var IRelationMap $mmapped */
$mmapped = $rel->multimap('a', 'b');
/** @var IRelationMap $mmapped2 */
$mmapped2 = $mmapped->multimap('c');

/** @var IMappedColumn $mcol */
$mcol = $mmapped2->col('d');
var_dump($mcol[1][2][3]); // column d containing rows having a=1, b=2, c=3
foreach ($mcol as $a => $bs) {
	foreach ($bs as $b => $cs) {
		foreach ($cs as $c => $col) {
			echo "$a => $b => $c:\n";
			foreach ($col as $val) {
				echo "$val\n";
			}
		}
	}
}

/** @var IMappedTuple $mtuple */
$mtuple = $mmapped2->tuple();
var_dump($mtuple[1][2][3]); // the first tuple having a=1, b=2, c=3
foreach ($mtuple as $a => $bs) {
	foreach ($bs as $b => $cs) {
		foreach ($cs as $c => $t) {
			echo "$a => $b => $c => $t[d]\n";
		}
	}
}


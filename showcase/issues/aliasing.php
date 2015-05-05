<?php
/**
 * Decide how to alias relations.
 */
namespace Ppg\Showcase\Issues;

use Ppg\Data\DbTableRelation;
use Ppg\Data\DbViewRelation;
use Ppg\Data\StatementRelation;


// VERSION 1

$personRel = new DbTableRelation('person');
$availPersonRel = new DbViewRelation('vw_avail_person', 'datatemplate');

$sql = new StatementRelation(
	"SELECT avail.room_id, %ln@p, 'the % sign is not expanded inside string literals'
	 FROM % avail
	      JOIN % p ON p.id = avail.person_id",
	$personRel->project('id', 'firstname', 'lastname'),
	$availPersonRel,
	$personRel
);
// "%ln" denotes a list of identifiers; in this case, it is fed with a relation, which causes to take all its attributes
// the "@" sign denotes aliasing the relation of attributes


// VERSION 2

$personRel = new DbTableRelation('person');
$personRel->aliased('p');
$availPersonRel = new DbViewRelation('vw_avail_person', 'datatemplate');

$sql = new StatementRelation(
	"SELECT avail.room_id, %ln, 'the % sign is not expanded inside string literals'
	 FROM %
	      JOIN % ON p.id = avail.person_id",
	$personRel->project('id', 'firstname', 'lastname'),
	$availPersonRel->aliased('avail'),
	$personRel
);
// "%ln" denotes a list of identifiers; in this case, it is fed with a relation, which causes to take all its attributes

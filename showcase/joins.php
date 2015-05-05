<?php
namespace Ppg\Showcase;

use Ppg\Data\DbTableRelation;
use Ppg\Data\DbViewRelation;

$personRel = new DbTableRelation('person');
$availPersonRel = new DbViewRelation('vw_avail_person', 'datatemplate');
//$rows = $availPersonRel->
// TODO: offer handy methods for joining two relations together

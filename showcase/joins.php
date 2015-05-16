<?php
namespace Ivory\Showcase;

use Ivory\Data\DbTableRelation;
use Ivory\Data\DbViewRelation;

$personRel = new DbTableRelation('person');
$availPersonRel = new DbViewRelation('vw_avail_person', 'datatemplate');
//$rows = $availPersonRel->
// TODO: offer handy methods for joining two relations together

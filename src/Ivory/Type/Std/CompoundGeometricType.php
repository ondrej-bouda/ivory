<?php
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;

/**
 * A common base for geometric types compound of points on a plane.
 */
abstract class CompoundGeometricType extends BaseType
{
    /** @var PointType */
    protected $pointType;


    public function __construct($name, $schemaName, $connection)
    {
        parent::__construct($name, $schemaName, $connection);

        $this->pointType = new PointType(null, null, $connection);
    }
}
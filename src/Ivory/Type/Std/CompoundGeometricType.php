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


    public function __construct($schemaName, $name, $connection)
    {
        parent::__construct($schemaName, $name, $connection);

        $this->pointType = new PointType(null, null, $connection);
    }
}

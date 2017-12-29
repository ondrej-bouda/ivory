<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Type\BaseType;

/**
 * A common base for geometric types compound of points on a plane.
 */
abstract class CompoundGeometricType extends BaseType
{
    /** @var PointType */
    protected $pointType;


    public function __construct(string $schemaName, string $name)
    {
        parent::__construct($schemaName, $name);

        $this->pointType = new PointType($schemaName, $name . '@' . PointType::class);
    }
}

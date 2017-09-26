<?php
declare(strict_types=1);

namespace Ivory\Type\Postgresql;

use Ivory\NamedDbObject;
use Ivory\Type\Postgresql\CompositeType;

/**
 * A composite type which has a name. That basically means the type is stored in the database.
 *
 * {@inheritdoc}
 */
class NamedCompositeType extends CompositeType
{
    use NamedDbObject;

    public function __construct(string $schemaName, string $name)
    {
        parent::__construct();

        $this->setName($schemaName, $name);
    }
}

<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;

abstract class ConnectionDependentBaseType extends BaseType implements IConnectionDependentObject
{
    /**
     * @param string $schemaName name of database schema of data type instead of which this object is to be created
     * @param string $name name of data type instead of which this object is to be created
     * @param IConnection|null $connection Ivory connection to attach the type to immediately;
     *                                     if not given, it is necessary to attach this type to the connection later,
     *                                       prior to any usage of this type
     */
    public function __construct(string $schemaName, string $name, IConnection $connection = null)
    {
        parent::__construct($schemaName, $name);

        if ($connection !== null) {
            $this->attachToConnection($connection);
        }
    }
}

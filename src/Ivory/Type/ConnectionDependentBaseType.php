<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;

abstract class ConnectionDependentBaseType extends BaseType implements IConnectionDependentType
{
    /**
     * @param string $schemaName name of database schema of data type instead of which this object is to be created
     * @param string $name name of data type instead of which this object is to be created
     * @param IConnection|null $connection Ivory connection to attach the type converter immediately;
     *                                     if not given, it is necessary to attach the connection later on
     */
    public function __construct(string $schemaName, string $name, IConnection $connection = null)
    {
        parent::__construct($schemaName, $name);

        if ($connection !== null) {
            $this->attachToConnection($connection);
        }
    }
}

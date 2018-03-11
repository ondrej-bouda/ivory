<?php
declare(strict_types=1);
namespace Ivory\Type\Ivory;

use Ivory\Connection\IConnection;
use Ivory\Type\ConnectionDependentObject;
use Ivory\Type\IConnectionDependentObject;
use Ivory\Type\IValueSerializer;

/**
 * A value serializer the operation of which depends on the database connection.
 */
abstract class ConnectionDependentValueSerializer implements IValueSerializer, IConnectionDependentObject
{
    use ConnectionDependentObject;

    public function __construct(?IConnection $connection = null)
    {
        if ($connection !== null) {
            $this->attachToConnection($connection);
        }
    }
}

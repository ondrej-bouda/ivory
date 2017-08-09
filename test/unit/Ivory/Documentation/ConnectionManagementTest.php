<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Ivory;
use Ivory\IvoryTestCase;

class ConnectionManagementTest extends IvoryTestCase
{
    public function testConnectionSetup()
    {
        $firstConn = Ivory::setupNewConnection('host=localhost dbname=mydb connect_timeout=10');
        $secondConn = Ivory::setupNewConnection('postgresql://usr@localhost:5433/otherdb', 'other');

        // The following assertions hold:
        self::assertSame($firstConn, Ivory::getConnection()); // the first is the default
        self::assertSame($secondConn, Ivory::getConnection('other')); // identified by the given name
        self::assertSame($firstConn, Ivory::getConnection('mydb')); // name generated from the database name
    }
}

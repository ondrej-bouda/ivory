<?php
declare(strict_types=1);

namespace Ivory\Documentation;

use Ivory\Ivory;

class ConnectionManagementTest extends \Ivory\IvoryTestCase
{
    public function testConnectionSetup()
    {
        $firstConn = Ivory::setupNewConnection('host=localhost dbname=mydb connect_timeout=10');
        $secondConn = Ivory::setupNewConnection('postgresql://usr@localhost:5433/otherdb', 'other');

        // The following assertions hold:
        $this->assertSame($firstConn, Ivory::getConnection()); // the first is the default
        $this->assertSame($secondConn, Ivory::getConnection('other')); // identified by the given name
        $this->assertSame($firstConn, Ivory::getConnection('mydb')); // connection name defaults to the database name
    }
}

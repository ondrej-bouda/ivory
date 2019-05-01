<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Exception\UnsupportedException;
use PHPUnit\Framework\TestCase;

class ConnectionParametersTest extends TestCase
{
    public function testFromConnectionString()
    {
        self::assertEquals(
            [
                ConnectionParameters::HOST => 'localhost',
                ConnectionParameters::PORT => 5432,
                ConnectionParameters::DBNAME => 'mydb',
                ConnectionParameters::CONNECT_TIMEOUT => 10,
            ],
            iterator_to_array(
                ConnectionParameters::fromConnectionString('host=localhost port=5432 dbname=mydb connect_timeout=10')
            )
        );

        self::assertEquals(
            [
                ConnectionParameters::USER => 'johndoe',
                ConnectionParameters::PASSWORD => '',
                ConnectionParameters::DBNAME => 'foo',
                ConnectionParameters::HOST => '/tmp',
                ConnectionParameters::OPTIONS => '-c geqo=off',
            ],
            iterator_to_array(
                ConnectionParameters::fromConnectionString(
                    "user = johndoe password='' dbname   =foo host=/tmp options='-c geqo=off'"
                )
            )
        );

        self::assertEquals(
            [],
            iterator_to_array(
                ConnectionParameters::fromConnectionString('')
            )
        );

        try {
            ConnectionParameters::fromConnectionString('bagr=');
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            ConnectionParameters::fromConnectionString("foo = 'incomplete");
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testFromUri()
    {
        self::assertEquals(
            [],
            iterator_to_array(
                ConnectionParameters::fromUri('postgresql://')
            )
        );

        self::assertEquals(
            [ConnectionParameters::HOST => 'localhost'],
            iterator_to_array(
                ConnectionParameters::fromUri('postgresql://localhost')
            )
        );

        self::assertEquals(
            [ConnectionParameters::HOST => 'localhost'],
            iterator_to_array(
                ConnectionParameters::fromUri('postgres://localhost')
            )
        );

        self::assertEquals(
            [ConnectionParameters::HOST => 'localhost', ConnectionParameters::PORT => 5433],
            iterator_to_array(
                ConnectionParameters::fromUri('postgresql://localhost:5433')
            )
        );

        self::assertEquals(
            [ConnectionParameters::HOST => 'localhost', ConnectionParameters::DBNAME => 'mydb'],
            iterator_to_array(
                ConnectionParameters::fromUri('postgresql://localhost/mydb')
            )
        );

        self::assertEquals(
            [ConnectionParameters::HOST => 'localhost', ConnectionParameters::USER => 'user'],
            iterator_to_array(
                ConnectionParameters::fromUri('postgresql://user@localhost')
            )
        );

        self::assertEquals(
            [
                ConnectionParameters::HOST => 'localhost',
                ConnectionParameters::USER => 'user',
                ConnectionParameters::PASSWORD => 'secret',
            ],
            iterator_to_array(
                ConnectionParameters::fromUri('postgresql://user:secret@localhost')
            )
        );

        self::assertEquals(
            [
                ConnectionParameters::HOST => 'localhost',
                ConnectionParameters::USER => 'other',
                ConnectionParameters::DBNAME => 'otherdb',
                ConnectionParameters::CONNECT_TIMEOUT => 10,
                ConnectionParameters::APPLICATION_NAME => 'myapp',
            ],
            iterator_to_array(
                ConnectionParameters::fromUri(
                    'postgresql://other@localhost/otherdb?connect_timeout=10&application_name=myapp'
                )
            )
        );

        self::assertEquals(
            [
                ConnectionParameters::DBNAME => 'mydb',
                ConnectionParameters::HOST => 'localhost',
                ConnectionParameters::PORT => 5433,
            ],
            iterator_to_array(
                ConnectionParameters::fromUri('postgresql:///mydb?host=localhost&port=5433')
            )
        );

        self::assertEquals(
            [ConnectionParameters::HOST => '[2001:db8::1234]', ConnectionParameters::DBNAME => 'database'],
            iterator_to_array(
                ConnectionParameters::fromUri('postgresql://[2001:db8::1234]/database')
            )
        );

        self::assertEquals(
            [ConnectionParameters::DBNAME => 'dbname', ConnectionParameters::HOST => '/var/lib/postgresql'],
            iterator_to_array(
                ConnectionParameters::fromUri('postgresql:///dbname?host=/var/lib/postgresql')
            )
        );

        self::assertEquals(
            [ConnectionParameters::HOST => '/var/lib/postgresql', ConnectionParameters::DBNAME => 'dbname'],
            iterator_to_array(
                ConnectionParameters::fromUri('postgresql://%2Fvar%2Flib%2Fpostgresql/dbname')
            )
        );

        try {
            ConnectionParameters::fromUri('localhost');
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            ConnectionParameters::fromUri('1234');
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            ConnectionParameters::fromUri('pgsql://localhost');
            self::fail('\Ivory\UnsupportedException expected');
        } catch (UnsupportedException $e) {
        }
    }

    public function testCreate()
    {
        self::assertEquals(
            [
                ConnectionParameters::HOST => 'localhost',
                ConnectionParameters::PORT => 5432,
                ConnectionParameters::DBNAME => 'mydb',
                ConnectionParameters::CONNECT_TIMEOUT => 10,
            ],
            iterator_to_array(
                ConnectionParameters::create('host=localhost port=5432 dbname=mydb connect_timeout=10')
            )
        );

        self::assertEquals(
            [
                ConnectionParameters::USER => 'johndoe',
                ConnectionParameters::PASSWORD => '',
                ConnectionParameters::DBNAME => 'foo',
                ConnectionParameters::HOST => '/tmp',
                ConnectionParameters::OPTIONS => '-c geqo=off',
            ],
            iterator_to_array(
                ConnectionParameters::create("user = johndoe password='' dbname   =foo host=/tmp options='-c geqo=off'")
            )
        );

        self::assertEquals(
            [],
            iterator_to_array(
                ConnectionParameters::create('')
            )
        );

        try {
            ConnectionParameters::create('bagr=');
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        try {
            ConnectionParameters::create("foo = 'incomplete");
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }

        self::assertEquals(
            [],
            iterator_to_array(
                ConnectionParameters::create('postgresql://')
            )
        );

        self::assertEquals(
            [ConnectionParameters::HOST => 'localhost', ConnectionParameters::DBNAME => 'mydb'],
            iterator_to_array(
                ConnectionParameters::create('postgresql://localhost/mydb')
            )
        );

        self::assertEquals(
            [ConnectionParameters::HOST => 'localhost', ConnectionParameters::DBNAME => 'mydb'],
            iterator_to_array(
                ConnectionParameters::create([
                    ConnectionParameters::HOST => 'localhost',
                    ConnectionParameters::DBNAME => 'mydb',
                ])
            )
        );

        $orig = ConnectionParameters::create([
            ConnectionParameters::HOST => 'localhost',
            ConnectionParameters::DBNAME => 'mydb',
            ]);
        self::assertEquals($orig, ConnectionParameters::create($orig));
        self::assertNotSame($orig, ConnectionParameters::create($orig));

        try {
            ConnectionParameters::create(1);
            self::fail('InvalidArgumentException expected');
        } catch (\InvalidArgumentException $e) {
        }
    }

    public function testGetters()
    {
        $cp1 = ConnectionParameters::fromUri('postgresql:///dbname?host=/var/lib/postgresql');
        self::assertSame('/var/lib/postgresql', $cp1->getHost());
        self::assertNull($cp1->getPort());
        self::assertNull($cp1->getUsername());
        self::assertNull($cp1->getPassword());
        self::assertSame('dbname', $cp1->getDbName());

        $cp2 = ConnectionParameters::fromConnectionString(
            'host=localhost port=5432 user=john password=doe dbname=mydb connect_timeout=10'
        );
        self::assertSame('localhost', $cp2->getHost());
        self::assertSame(5432, $cp2->getPort());
        self::assertSame('john', $cp2->getUsername());
        self::assertSame('doe', $cp2->getPassword());
        self::assertSame('mydb', $cp2->getDbName());
    }

    public function testArrayAccess()
    {
        $cp1 = ConnectionParameters::fromUri('postgresql:///dbname?host=/var/lib/postgresql');
        self::assertSame('/var/lib/postgresql', $cp1[ConnectionParameters::HOST]);
        self::assertFalse(isset($cp1[ConnectionParameters::PORT]));
        self::assertSame('dbname', $cp1[ConnectionParameters::DBNAME]);

        $cp2 = ConnectionParameters::fromConnectionString(
            'host=localhost port=5432 user=john password=doe dbname=mydb connect_timeout=10'
        );
        self::assertSame('5432', $cp2[ConnectionParameters::PORT]);
        self::assertSame('john', $cp2[ConnectionParameters::USER]);
        self::assertSame('10', $cp2[ConnectionParameters::CONNECT_TIMEOUT]);
        self::assertTrue(isset($cp2[ConnectionParameters::PASSWORD]));
        unset($cp2[ConnectionParameters::PASSWORD]);
        self::assertFalse(isset($cp2[ConnectionParameters::PASSWORD]));
        $cp2[ConnectionParameters::PASSWORD] = 1234;
        self::assertTrue(isset($cp2[ConnectionParameters::PASSWORD]));
        self::assertSame('1234', $cp2[ConnectionParameters::PASSWORD]);
    }

    public function testBuildConnectionString()
    {
        self::assertSame(
            'dbname=dbname host=/var/lib/postgresql',
            ConnectionParameters::fromUri('postgresql:///dbname?host=/var/lib/postgresql')->buildConnectionString()
        );

        self::assertSame(
            'host=localhost port=5432 user=john password=doe dbname=mydb connect_timeout=10',
            ConnectionParameters::fromConnectionString(
                'host=localhost port=5432 user=john password=doe dbname=mydb connect_timeout=10'
            )->buildConnectionString()
        );

        self::assertSame(
            'host=[2001:db8::1234] dbname=database',
            ConnectionParameters::fromUri('postgresql://[2001:db8::1234]/database')->buildConnectionString()
        );

        self::assertSame(
            "user=johndoe password='' dbname=foo host=/tmp options='-c geqo=off'",
            ConnectionParameters::fromConnectionString(
                "user = johndoe password='' dbname   =foo host=/tmp options='-c geqo=off'"
            )->buildConnectionString()
        );

        self::assertSame('', ConnectionParameters::fromArray([])->buildConnectionString());

        self::assertSame(
            "user='john doe' password='' opt='\\'' opt2='\\\\\\''",
            ConnectionParameters::fromArray([
                'user' => 'john doe',
                'password' => '',
                'opt' => "'",
                'opt2' => "\\'",
            ])->buildConnectionString()
        );
    }
}

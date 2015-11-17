<?php
namespace Ivory\Connection;

class ConnectionParametersTest extends \PHPUnit_Framework_TestCase
{
	public function testFromConnectionString()
	{
		$this->assertEquals(
			['host' => 'localhost', 'port' => 5432, 'dbname' => 'mydb', 'connect_timeout' => 10],
			iterator_to_array(
				ConnectionParameters::fromConnectionString('host=localhost port=5432 dbname=mydb connect_timeout=10')
			)
		);

		$this->assertEquals(
			['user' => 'johndoe', 'password' => '', 'dbname' => 'foo', 'host' => '/tmp', 'options' => '-c geqo=off'],
			iterator_to_array(
				ConnectionParameters::fromConnectionString("user = johndoe password='' dbname   =foo host=/tmp options='-c geqo=off'")
			)
		);

		$this->assertEquals(
			[],
			iterator_to_array(
				ConnectionParameters::fromConnectionString('')
			)
		);

		try {
			ConnectionParameters::fromConnectionString('bagr=');
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) {}

		try {
			ConnectionParameters::fromConnectionString("foo = 'incomplete");
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) {}
	}

	public function testFromUri()
	{
		$this->assertEquals(
			[],
			iterator_to_array(
				ConnectionParameters::fromUri('postgresql://')
			)
		);

		$this->assertEquals(
			['host' => 'localhost'],
			iterator_to_array(
				ConnectionParameters::fromUri('postgresql://localhost')
			)
		);

		$this->assertEquals(
			['host' => 'localhost'],
			iterator_to_array(
				ConnectionParameters::fromUri('postgres://localhost')
			)
		);

		$this->assertEquals(
			['host' => 'localhost', 'port' => 5433],
			iterator_to_array(
				ConnectionParameters::fromUri('postgresql://localhost:5433')
			)
		);

		$this->assertEquals(
			['host' => 'localhost', 'dbname' => 'mydb'],
			iterator_to_array(
				ConnectionParameters::fromUri('postgresql://localhost/mydb')
			)
		);

		$this->assertEquals(
			['host' => 'localhost', 'user' => 'user'],
			iterator_to_array(
				ConnectionParameters::fromUri('postgresql://user@localhost')
			)
		);

		$this->assertEquals(
			['host' => 'localhost', 'user' => 'user', 'password' => 'secret'],
			iterator_to_array(
				ConnectionParameters::fromUri('postgresql://user:secret@localhost')
			)
		);

		$this->assertEquals(
			['host' => 'localhost', 'user' => 'other', 'dbname' => 'otherdb', 'connect_timeout' => 10, 'application_name' => 'myapp'],
			iterator_to_array(
				ConnectionParameters::fromUri('postgresql://other@localhost/otherdb?connect_timeout=10&application_name=myapp')
			)
		);

		$this->assertEquals(
			['dbname' => 'mydb', 'host' => 'localhost', 'port' => 5433],
			iterator_to_array(
				ConnectionParameters::fromUri('postgresql:///mydb?host=localhost&port=5433')
			)
		);

		$this->assertEquals(
			['host' => '[2001:db8::1234]', 'dbname' => 'database'],
			iterator_to_array(
				ConnectionParameters::fromUri('postgresql://[2001:db8::1234]/database')
			)
		);

		$this->assertEquals(
			['dbname' => 'dbname', 'host' => '/var/lib/postgresql'],
			iterator_to_array(
				ConnectionParameters::fromUri('postgresql:///dbname?host=/var/lib/postgresql')
			)
		);

		$this->assertEquals(
			['host' => '/var/lib/postgresql', 'dbname' => 'dbname'],
			iterator_to_array(
				ConnectionParameters::fromUri('postgresql://%2Fvar%2Flib%2Fpostgresql/dbname')
			)
		);

		try {
			ConnectionParameters::fromUri('localhost');
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) {}

		try {
			ConnectionParameters::fromUri('1234');
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) {}

		try {
			ConnectionParameters::fromUri('pgsql://localhost');
			$this->fail('\Ivory\UnsupportedException expected');
		}
		catch (\Ivory\Exception\UnsupportedException $e) {}
	}

	public function testCreate()
	{
		$this->assertEquals(
			['host' => 'localhost', 'port' => 5432, 'dbname' => 'mydb', 'connect_timeout' => 10],
			iterator_to_array(
				ConnectionParameters::create('host=localhost port=5432 dbname=mydb connect_timeout=10')
			)
		);

		$this->assertEquals(
			['user' => 'johndoe', 'password' => '', 'dbname' => 'foo', 'host' => '/tmp', 'options' => '-c geqo=off'],
			iterator_to_array(
				ConnectionParameters::create("user = johndoe password='' dbname   =foo host=/tmp options='-c geqo=off'")
			)
		);

		$this->assertEquals(
			[],
			iterator_to_array(
				ConnectionParameters::create('')
			)
		);

		try {
			ConnectionParameters::create('bagr=');
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) {}

		try {
			ConnectionParameters::create("foo = 'incomplete");
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) {}

		$this->assertEquals(
			[],
			iterator_to_array(
				ConnectionParameters::create('postgresql://')
			)
		);

		$this->assertEquals(
			['host' => 'localhost', 'dbname' => 'mydb'],
			iterator_to_array(
				ConnectionParameters::create('postgresql://localhost/mydb')
			)
		);

		$this->assertEquals(
			['host' => 'localhost', 'dbname' => 'mydb'],
			iterator_to_array(
				ConnectionParameters::create(['host' => 'localhost', 'dbname' => 'mydb'])
			)
		);

		$orig = ConnectionParameters::create(['host' => 'localhost', 'dbname' => 'mydb']);
		$this->assertEquals($orig, ConnectionParameters::create($orig));
		$this->assertNotSame($orig, ConnectionParameters::create($orig));

		try {
			ConnectionParameters::create(1);
			$this->fail('InvalidArgumentException expected');
		}
		catch (\InvalidArgumentException $e) {
		}
	}

	public function testGetters()
	{
		$cp1 = ConnectionParameters::fromUri('postgresql:///dbname?host=/var/lib/postgresql');
		$this->assertSame('/var/lib/postgresql', $cp1->getHost());
		$this->assertNull($cp1->getPort());
		$this->assertNull($cp1->getUsername());
		$this->assertNull($cp1->getPassword());
		$this->assertSame('dbname', $cp1->getDbName());

		$cp2 = ConnectionParameters::fromConnectionString('host=localhost port=5432 user=john password=doe dbname=mydb connect_timeout=10');
		$this->assertSame('localhost', $cp2->getHost());
		$this->assertSame(5432, $cp2->getPort());
		$this->assertSame('john', $cp2->getUsername());
		$this->assertSame('doe', $cp2->getPassword());
		$this->assertSame('mydb', $cp2->getDbName());
	}

	public function testArrayAccess()
	{
		$cp1 = ConnectionParameters::fromUri('postgresql:///dbname?host=/var/lib/postgresql');
		$this->assertSame('/var/lib/postgresql', $cp1['host']);
		$this->assertFalse(isset($cp1['port']));
		$this->assertSame('dbname', $cp1['dbname']);

		$cp2 = ConnectionParameters::fromConnectionString('host=localhost port=5432 user=john password=doe dbname=mydb connect_timeout=10');
		$this->assertSame('5432', $cp2['port']);
		$this->assertSame('john', $cp2['user']);
		$this->assertSame('10', $cp2['connect_timeout']);
		$this->assertTrue(isset($cp2['password']));
		unset($cp2['password']);
		$this->assertFalse(isset($cp2['password']));
		$cp2['password'] = 1234;
		$this->assertTrue(isset($cp2['password']));
		$this->assertSame('1234', $cp2['password']);
	}

	public function testBuildConnectionString()
	{
		$this->assertSame(
			'dbname=dbname host=/var/lib/postgresql',
			ConnectionParameters::fromUri('postgresql:///dbname?host=/var/lib/postgresql')->buildConnectionString()
		);

		$this->assertSame(
			'host=localhost port=5432 user=john password=doe dbname=mydb connect_timeout=10',
			ConnectionParameters::fromConnectionString('host=localhost port=5432 user=john password=doe dbname=mydb connect_timeout=10')
				->buildConnectionString()
		);

		$this->assertSame(
			'host=[2001:db8::1234] dbname=database',
			ConnectionParameters::fromUri('postgresql://[2001:db8::1234]/database')->buildConnectionString()
		);

		$this->assertSame(
			"user=johndoe password='' dbname=foo host=/tmp options='-c geqo=off'",
			ConnectionParameters::fromConnectionString("user = johndoe password='' dbname   =foo host=/tmp options='-c geqo=off'")
				->buildConnectionString()
		);

		$this->assertSame('', (new ConnectionParameters([]))->buildConnectionString());

		$this->assertSame(
			"user='john doe' password='' opt='\\'' opt2='\\\\\\''",
			(new ConnectionParameters([
				'user' => 'john doe',
				'password' => '',
				'opt' => "'",
				'opt2' => "\\'",
			]))->buildConnectionString()
		);
	}
}
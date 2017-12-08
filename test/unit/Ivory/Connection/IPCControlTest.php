<?php
declare(strict_types=1);

namespace Ivory\Connection;

class IPCControlTest extends \Ivory\IvoryTestCase
{
    /** @var IConnection */
    private $conn1;
    /** @var IConnection */
    private $conn2;

    protected function setUp()
    {
        parent::setUp();

        $this->conn1 = $this->createNewIvoryConnection('conn1');
        $this->conn2 = $this->createNewIvoryConnection('conn2');
    }

    protected function tearDown()
    {
        parent::tearDown();

        $this->conn1->disconnect();
        $this->conn2->disconnect();
    }


    public function testSimpleListen()
    {
        $this->conn1->listen('test');
        $this->assertNull($this->conn1->pollNotification());

        $this->conn2->notify('test');
        $notification = $this->conn1->pollNotification();

        $this->assertNotNull($notification);
        $this->assertSame('test', $notification->getChannel());
        $this->assertNull($notification->getPayload());
        $this->assertSame($this->conn2->getBackendPID(), $notification->getSenderBackendPID());
    }

    public function testComplexListen()
    {
        $this->conn1->listen('t1');
        $this->conn1->listen('t2');
        $this->conn1->listen('t3');
        $this->conn1->unlisten('t3');

        $this->assertNull($this->conn1->pollNotification());

        $this->conn2->notify('t1', 'a');
        $notification1 = $this->conn1->pollNotification();
        $this->assertNotNull($notification1);
        $this->assertSame('t1', $notification1->getChannel());
        $this->assertSame('a', $notification1->getPayload());

        $this->assertNull($this->conn1->pollNotification());

        $this->conn2->notify('t2');
        $notification2 = $this->conn1->pollNotification();
        $this->assertNotNull($notification2);
        $this->assertSame('t2', $notification2->getChannel());
        $this->assertNull($notification2->getPayload());

        $this->assertNull($this->conn1->pollNotification());

        $this->conn2->notify('t3');
        $this->assertNull($this->conn1->pollNotification());

        $this->conn1->unlistenAll();

        $this->conn2->notify('t1');
        $this->assertNull($this->conn1->pollNotification());

        $this->conn2->notify('t2');
        $this->assertNull($this->conn1->pollNotification());
    }

    public function testQueue()
    {
        $this->conn1->listen('t');
        $this->conn2->notify('t');
        $this->conn2->notify('t', 'b');
        $this->conn2->notify('t', 'c');

        $this->assertNull($this->conn1->pollNotification()->getPayload());
        $this->assertSame('b', $this->conn1->pollNotification()->getPayload());

        $this->conn2->notify('t', 'd');

        $this->assertSame('c', $this->conn1->pollNotification()->getPayload());
        $this->assertSame('d', $this->conn1->pollNotification()->getPayload());

        $this->assertNull($this->conn1->pollNotification());
    }
}

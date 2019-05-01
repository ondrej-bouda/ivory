<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\IvoryTestCase;

class IPCControlTest extends IvoryTestCase
{
    private const WAIT_TIMEOUT = 1000;

    /** @var IConnection */
    private $conn1;
    /** @var IConnection */
    private $conn2;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn1 = $this->createNewIvoryConnection('conn1');
        $this->conn2 = $this->createNewIvoryConnection('conn2');
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        $this->conn1->disconnect();
        $this->conn2->disconnect();
    }


    public function testSimpleListenPoll()
    {
        $this->conn1->listen('test');
        self::assertNull($this->conn1->pollNotification());

        $this->conn2->notify('test');
        $safetyBreak = 100;
        while (($notification = $this->conn1->pollNotification()) === null) {
            if (--$safetyBreak < 0) {
                break;
            }
            usleep(100000); // no better way to test polling
        }

        self::assertNotNull($notification);
        self::assertSame('test', $notification->getChannel());
        self::assertNull($notification->getPayload());
        self::assertSame($this->conn2->getBackendPID(), $notification->getSenderBackendPID());
    }

    public function testSimpleListenWait()
    {
        $this->conn1->listen('test');
        self::assertNull($this->conn1->waitForNotification(self::WAIT_TIMEOUT));

        $this->conn2->notify('test');
        $notification = $this->conn1->waitForNotification(self::WAIT_TIMEOUT);

        self::assertNotNull($notification);
        self::assertSame('test', $notification->getChannel());
        self::assertNull($notification->getPayload());
        self::assertSame($this->conn2->getBackendPID(), $notification->getSenderBackendPID());
    }

    public function testComplexListen()
    {
        $this->conn1->listen('t1');
        $this->conn1->listen('t2');
        $this->conn1->listen('t3');
        $this->conn1->unlisten('t3');

        self::assertNull($this->conn1->waitForNotification(self::WAIT_TIMEOUT));

        $this->conn2->notify('t1', 'a');
        $notification1 = $this->conn1->waitForNotification(self::WAIT_TIMEOUT);
        self::assertNotNull($notification1);
        self::assertSame('t1', $notification1->getChannel());
        self::assertSame('a', $notification1->getPayload());

        self::assertNull($this->conn1->waitForNotification(self::WAIT_TIMEOUT));

        $this->conn2->notify('t2');
        $notification2 = $this->conn1->waitForNotification(self::WAIT_TIMEOUT);
        self::assertNotNull($notification2);
        self::assertSame('t2', $notification2->getChannel());
        self::assertNull($notification2->getPayload());

        self::assertNull($this->conn1->waitForNotification(self::WAIT_TIMEOUT));

        $this->conn2->notify('t3');
        self::assertNull($this->conn1->waitForNotification(self::WAIT_TIMEOUT));

        $this->conn1->unlistenAll();

        $this->conn2->notify('t1');
        self::assertNull($this->conn1->waitForNotification(self::WAIT_TIMEOUT));

        $this->conn2->notify('t2');
        self::assertNull($this->conn1->waitForNotification(self::WAIT_TIMEOUT));
    }

    public function testQueue()
    {
        $this->conn1->listen('t');
        $this->conn2->notify('t');
        $this->conn2->notify('t', 'b');
        $this->conn2->notify('t', 'c');

        $notif1 = $this->conn1->waitForNotification(self::WAIT_TIMEOUT);
        self::assertNotNull($notif1);
        self::assertNull($notif1->getPayload());

        $notif2 = $this->conn1->waitForNotification(self::WAIT_TIMEOUT);
        self::assertSame('b', $notif2->getPayload());

        $this->conn2->notify('t', 'd');

        $notif3 = $this->conn1->waitForNotification(self::WAIT_TIMEOUT);
        self::assertSame('c', $notif3->getPayload());

        $notif4 = $this->conn1->waitForNotification(self::WAIT_TIMEOUT);
        self::assertSame('d', $notif4->getPayload());

        $notif5 = $this->conn1->waitForNotification(self::WAIT_TIMEOUT);
        self::assertNull($notif5);
    }
}

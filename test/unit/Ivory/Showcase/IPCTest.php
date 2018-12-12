<?php
declare(strict_types=1);
namespace Ivory\Showcase;

use Ivory\Connection\IConnection;
use Ivory\IvoryTestCase;

/**
 * This test presents the inter-process communication support.
 *
 * PostgreSQL offers a simple LISTEN/NOTIFY system for IPC. It is encapsulated in Ivory within the
 * {@link Ivory\Connection\IIPCControl} part of the connection.
 *
 * When listening for notifications, both non-blocking {@link Ivory\Connection\IIPCControl::pollNotification() polling}
 * and blocking {@link Ivory\Connection\IIPCControl::waitForNotification() waiting} is supported.
 */
class IPCTest extends IvoryTestCase
{
    private const CHANNEL = self::class;

    public static function notifierProcess(IConnection $conn): void
    {
        $conn->notify(self::CHANNEL, 'Starting IPC test...');
        $conn->notify(self::CHANNEL, 'Sleeping for 200 ms');
        usleep(200000);
        $conn->notify(self::CHANNEL, 'Woke up. Sleeping for another 5000 ms');
        usleep(5000000);
        $conn->notify(self::CHANNEL, 'Woke up again. Done.');
    }

    private function runIPCTestNotifierInBackground()
    {
        $phpScript = __DIR__ . '/IPCTestNotifier.php';
        $cmd = PHP_BINARY . ' ' . $phpScript;

        if (strncasecmp(PHP_OS, 'WIN', 3) == 0) {
            exec("start /B wmic process call create \"$cmd\"");
        } else {
            exec("$cmd &");
        }
    }

    public function testPollingForNotification()
    {
        $conn = $this->getIvoryConnection();
        $conn->listen(self::CHANNEL);

        $this->runIPCTestNotifierInBackground();

        $n1 = null;
        for ($i = 0; $i < 1000; $i++) {
            $n1 = $conn->pollNotification();
            if ($n1 !== null) {
                break;
            }
            usleep(10000);
        }
        $this->assertNotNull($n1);
        $this->assertSame('Starting IPC test...', $n1->getPayload());

        $n2 = null;
        for ($i = 0; $i < 1000; $i++) {
            $n2 = $conn->pollNotification();
            if ($n2 !== null) {
                break;
            }
            usleep(10000);
        }
        $this->assertNotNull($n2);
        $this->assertSame('Sleeping for 200 ms', $n2->getPayload());

        $n3 = null;
        for ($i = 0; $i < 1000; $i++) {
            $n3 = $conn->pollNotification();
            if ($n3 !== null) {
                break;
            }
            usleep(10000);
        }
        $this->assertNotNull($n3);
        $this->assertSame('Woke up. Sleeping for another 5000 ms', $n3->getPayload());

        $n4 = null;
        for ($i = 0; $i < 1000; $i++) {
            $n4 = $conn->pollNotification();
            if ($n4 !== null) {
                break;
            }
            usleep(10000);
        }
        $this->assertNotNull($n4);
        $this->assertSame('Woke up again. Done.', $n4->getPayload());

        $n5 = null;
        for ($i = 0; $i < 1000; $i++) {
            $n5 = $conn->pollNotification();
            if ($n5 !== null) {
                break;
            }
            usleep(10000);
        }
        $this->assertNull($n5);
    }

    public function testWaitingForNotification()
    {
        $conn = $this->getIvoryConnection();
        $conn->listen(self::CHANNEL);

        $this->runIPCTestNotifierInBackground();

        $n1 = $conn->waitForNotification(10000);
        $this->assertNotNull($n1);
        $this->assertSame('Starting IPC test...', $n1->getPayload());

        $n2 = $conn->waitForNotification(10000);
        $this->assertNotNull($n2);
        $this->assertSame('Sleeping for 200 ms', $n2->getPayload());

        $n3 = $conn->waitForNotification(10000);
        $this->assertNotNull($n3);
        $this->assertSame('Woke up. Sleeping for another 5000 ms', $n3->getPayload());

        $n4 = $conn->waitForNotification(10000);
        $this->assertNotNull($n4);
        $this->assertSame('Woke up again. Done.', $n4->getPayload());

        $n5 = $conn->waitForNotification(10000);
        $this->assertNull($n5);
    }
}

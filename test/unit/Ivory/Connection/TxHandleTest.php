<?php
declare(strict_types=1);

namespace Ivory\Connection;

class TxHandleTest extends \Ivory\IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp()
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
    }

    public function testRollbackIfOpen()
    {
        $observer = new class() extends TransactionControlObserverBase
        {
            public $rollbackCalled = 0;

            public function handleTransactionRollback(): void
            {
                $this->rollbackCalled++;
            }
        };
        $this->conn->addTransactionControlObserver($observer);

        $tx = $this->conn->startTransaction();
        $this->assertTrue($this->conn->inTransaction());
        $this->assertEquals(0, $observer->rollbackCalled);

        $tx->rollbackIfOpen();
        $this->assertFalse($this->conn->inTransaction());
        $this->assertEquals(1, $observer->rollbackCalled);

        $tx->rollbackIfOpen();
        $this->assertFalse($this->conn->inTransaction());
        $this->assertEquals(1, $observer->rollbackCalled);
    }

    public function testOrphanedOpen()
    {
        $tx = $this->conn->startTransaction();

        try {
            unset($tx);
            $this->fail('A warning was expected due to destroying an open transaction handle.');
        } catch (\PHPUnit\Framework\Error\Warning $warning) {
            $this->assertContains(
                'open', $warning->getMessage(),
                'The warning message should mention the transaction handle stayed open', true
            );
        }
    }

    public function testSnapshots()
    {
        $conn1 = $this->createNewIvoryConnection();
        $conn1->connect();
        $conn2 = $this->createNewIvoryConnection();
        $conn2->connect();

        $tx1 = null;
        $tx2 = null;

        $tableName = 't' . mt_rand(1000, 9999);
        $this->conn->command('CREATE TABLE %ident (i INT)', $tableName);
        try {
            $tx1 = $conn1->startTransaction(TxConfig::ISOLATION_REPEATABLE_READ);

            $this->assertSame(0, $conn1->querySingleValue('SELECT COUNT(*) FROM %ident', $tableName));

            $this->conn->command('INSERT INTO %ident (i) VALUES (1)', $tableName);

            $this->assertSame(0, $conn1->querySingleValue('SELECT COUNT(*) FROM %ident', $tableName));
            $this->assertSame(1, $conn2->querySingleValue('SELECT COUNT(*) FROM %ident', $tableName));

            $snapshotId = $tx1->exportTransactionSnapshot();

            $tx2 = $conn2->startTransaction(TxConfig::ISOLATION_REPEATABLE_READ);
            $tx2->setTransactionSnapshot($snapshotId);

            $this->assertSame(0, $conn2->querySingleValue('SELECT COUNT(*) FROM %ident', $tableName));
        } finally {
            if ($tx1 !== null) {
                $tx1->rollback();
            }
            if ($tx2 !== null) {
                $tx2->rollback();
            }

            $conn1->disconnect();
            $conn2->disconnect();

            $this->conn->command('DROP TABLE %ident', $tableName);
        }
    }
}

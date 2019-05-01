<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\IvoryTestCase;

class TransactionControlTest extends IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp(): void
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
    }

    public function testRollbackIfOpen()
    {
        $observer = new class() extends TransactionControlObserverBase
        {
            public $rollbackCalled = 0;

            /** @noinspection PhpMissingParentCallCommonInspection */
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
        $conn1 = $this->createNewIvoryConnection('conn1');
        $conn1->connect();
        $conn2 = $this->createNewIvoryConnection('conn2');
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

    public function testConfig()
    {
        $conn1 = $this->createNewIvoryConnection('conn1');
        $tx = null;
        try {
            $conn1->setupSubsequentTransactions(TxConfig::create(
                TxConfig::ISOLATION_READ_UNCOMMITTED | TxConfig::ACCESS_READ_WRITE
            ));
            $newDefaultConfig = $conn1->getDefaultTxConfig();
            $this->assertSame(TxConfig::ISOLATION_READ_UNCOMMITTED, $newDefaultConfig->getIsolationLevel());
            $this->assertFalse($newDefaultConfig->isReadOnly());

            $tx = $conn1->startTransaction();
            $actualConfig = $tx->getTxConfig();
            $this->assertSame(TxConfig::ISOLATION_READ_UNCOMMITTED, $actualConfig->getIsolationLevel());
            $this->assertFalse($actualConfig->isReadOnly());
            $this->assertFalse($actualConfig->isDeferrable());
            $tx->rollback();

            $tx = $conn1->startTransaction(TxConfig::ACCESS_READ_ONLY | TxConfig::NOT_DEFERRABLE);
            $tx->setupTransaction(TxConfig::create(TxConfig::ISOLATION_SERIALIZABLE));
            $actualConfig = $tx->getTxConfig();
            $this->assertSame(TxConfig::ISOLATION_SERIALIZABLE, $actualConfig->getIsolationLevel());
            $this->assertTrue($actualConfig->isReadOnly());
            $this->assertFalse($actualConfig->isDeferrable());
        } finally {
            if ($tx !== null) {
                $tx->rollbackIfOpen();
            }
            $conn1->disconnect();
        }
    }

    public function testPrepareTransaction()
    {
        $conn1 = $this->createNewIvoryConnection('conn1');
        $tx = null;
        try {
            $tx = $conn1->startTransaction();
            $txName1 = $tx->prepareTransaction();
            $this->assertTrue(strlen($txName1) >= 8);
            $conn1->rollbackPreparedTransaction($txName1);

            $tx = $conn1->startTransaction();
            $txName2 = $tx->prepareTransaction();
            $this->assertTrue(strlen($txName2) >= 8);
            $conn1->rollbackPreparedTransaction($txName2);

            $this->assertNotSame($txName1, $txName2);
        } finally {
            if ($tx !== null) {
                $tx->rollbackIfOpen();
            }
            $conn1->disconnect();
        }
    }
}

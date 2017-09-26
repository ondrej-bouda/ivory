<?php
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
}

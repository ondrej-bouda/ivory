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

<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Connection\IObservableTransactionControl;
use Ivory\Connection\ISessionControl;
use Ivory\Connection\IStatementExecution;
use Ivory\Connection\ITxHandle;
use Ivory\Connection\TracingTxHandle;
use Ivory\Ivory;
use Ivory\IvoryTestCase;
use Ivory\StdCoreFactory;
use PHPUnit\Framework\Error\Warning;

class TracingTxHandleTest extends IvoryTestCase
{
    private $origCoreFactory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->origCoreFactory = Ivory::getCoreFactory();
        Ivory::setCoreFactory(
            new class extends StdCoreFactory
            {
                /** @noinspection PhpMissingParentCallCommonInspection */
                public function createTransactionHandle(
                    IStatementExecution $stmtExec,
                    IObservableTransactionControl $observableTxCtl,
                    ISessionControl $sessionCtl
                ): ITxHandle {
                    return new TracingTxHandle($stmtExec, $observableTxCtl, $sessionCtl);
                }
            }
        );
    }

    protected function tearDown(): void
    {
        Ivory::setCoreFactory($this->origCoreFactory);
        parent::tearDown();
    }

    public function testStackTraceReported()
    {
        $conn = $this->getIvoryConnection();
        try {
            $tx = $conn->startTransaction();
            self::assertInstanceOf(TracingTxHandle::class, $tx);
            unset($tx);
            self::fail('A warning should have been thrown');
        } catch (Warning $warning) {
            self::assertContains(__FILE__, $warning->getMessage());
        } finally {
            $conn->command('ROLLBACK');
        }
    }
}

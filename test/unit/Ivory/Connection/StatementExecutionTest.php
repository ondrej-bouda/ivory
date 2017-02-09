<?php
namespace Ivory\Connection;

use Ivory\Exception\StatementException;
use Ivory\Result\SqlState;
use Ivory\Result\SqlStateClass;

class StatementExecutionTest extends \Ivory\IvoryTestCase
{
    /** @var IStatementExecution */
    private $conn;

    protected function setUp()
    {
        $this->conn = $this->getIvoryConnection();
    }


    public function testQuery()
    {
        $result = $this->conn->query('SELECT 1');
        $this->assertSame(1, $result->count());
        $this->assertSame(1, count($result->getColumns()));

        // TODO: test the following once IStatementExecution::rawQuery() gets distinguished into query and command
//        $result = @$this->conn->query("DO LANGUAGE plpgsql 'BEGIN END'");
//        $this->assertEmpty($result->count());
//        $this->assertEmpty($result->getColumns());
//
//        try {
//            $this->conn->query("DO LANGUAGE plpgsql 'BEGIN END'");
//            $this->fail('A warning was expected due to command executed using query().');
//        }
//        catch (\PHPUnit_Framework_Error_Warning $warning) {
//            $this->assertContains('query', $warning->getMessage(), 'The warning message should mention a query was expected', true);
//            $this->assertContains('command', $warning->getMessage(), 'The warning message should mention command was actually executed', true);
//        }
    }

    public function testCommand()
    {
        $result = $this->conn->command("DO LANGUAGE plpgsql 'BEGIN END'");
        $this->assertSame(0, $result->getAffectedRows());
        $this->assertSame('DO', $result->getCommandTag());

        // TODO: test the following once IStatementExecution::rawQuery() gets distinguished into query and command
//        $result = @$this->conn->command('SELECT 1');
//        $this->assertSame(0, $result->getAffectedRows());
//        $this->assertSame('SELECT 1', $result->getCommandTag());
//
//        try {
//            $this->conn->command('SELECT 1');
//            $this->fail('A warning was expected due to query executed using command().');
//        }
//        catch (\PHPUnit_Framework_Error_Warning $warning) {
//            $this->assertContains('command', $warning->getMessage(), 'The warning message should mention command was expected', true);
//            $this->assertContains('query', $warning->getMessage(), 'The warning message should mention a query was actually executed', true);
//        }
    }

    public function testQueryError()
    {
        try {
            $this->conn->query('SELECT log(-10)');
            $this->fail('Error expected');
        }
        catch (StatementException $e) {
            $this->assertSame(SqlState::INVALID_ARGUMENT_FOR_LOGARITHM, $e->getSqlStateCode());
        }
    }

    public function testCustomException()
    {
        $stmtExFactory = $this->conn->getStatementExceptionFactory();

        $stmtExFactory->registerByMessage('~logarithm~', StatementExecutionTest__LogarithmException::class);
        try {
            $this->conn->query('SELECT log(-10)');
            $this->fail('Error expected');
        }
        catch (StatementExecutionTest__LogarithmException $e) {
        }
        finally {
            $stmtExFactory->clear();
        }

        $stmtExFactory->registerBySqlStateCode(SqlState::INVALID_ARGUMENT_FOR_LOGARITHM, StatementExecutionTest__LogarithmException::class);
        try {
            $this->conn->query('SELECT log(-10)');
            $this->fail('Error expected');
        }
        catch (StatementExecutionTest__LogarithmException $e) {
        }
        finally {
            $stmtExFactory->clear();
        }

        $stmtExFactory->registerBySqlStateClass(SqlStateClass::DATA_EXCEPTION, StatementExecutionTest__LogarithmException::class);
        try {
            $this->conn->query('SELECT log(-10)');
            $this->fail('Error expected');
        }
        catch (StatementExecutionTest__LogarithmException $e) {
        }
        finally {
            $stmtExFactory->clear();
        }
    }
}

class StatementExecutionTest__LogarithmException extends StatementException
{
}

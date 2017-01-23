<?php
namespace Ivory\Connection;

use Ivory\Exception\StatementException;
use Ivory\Relation\QueryRelation;
use Ivory\Result\SqlState;
use Ivory\Result\SqlStateClass;

class StatementExecutionTest extends \Ivory\IvoryTestCase
{
    public function testQueryError()
    {
        $conn = $this->getIvoryConnection();
        $rel = new QueryRelation($conn, 'SELECT log(-10)');
        try {
            $rel->value();
            $this->fail('Error expected');
        }
        catch (StatementException $e) {
            $this->assertSame(SqlState::INVALID_ARGUMENT_FOR_LOGARITHM, $e->getSqlStateCode());
        }
    }

    public function testCustomException()
    {
        $conn = $this->getIvoryConnection();
        $stmtExFactory = $conn->getStatementExceptionFactory();

        $stmtExFactory->registerByMessage('~logarithm~', StatementExecutionTest__LogarithmException::class);
        try {
            $rel = new QueryRelation($conn, 'SELECT log(-10)');
            $rel->value();
            $this->fail('Error expected');
        }
        catch (StatementExecutionTest__LogarithmException $e) {
        }
        finally {
            $stmtExFactory->clear();
        }

        $stmtExFactory->registerBySqlStateCode(SqlState::INVALID_ARGUMENT_FOR_LOGARITHM, StatementExecutionTest__LogarithmException::class);
        try {
            $rel = new QueryRelation($conn, 'SELECT log(-10)');
            $rel->value();
            $this->fail('Error expected');
        }
        catch (StatementExecutionTest__LogarithmException $e) {
        }
        finally {
            $stmtExFactory->clear();
        }

        $stmtExFactory->registerBySqlStateClass(SqlStateClass::DATA_EXCEPTION, StatementExecutionTest__LogarithmException::class);
        try {
            $rel = new QueryRelation($conn, 'SELECT log(-10)');
            $rel->value();
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

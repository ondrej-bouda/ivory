<?php
namespace Ivory\Connection;

use Ivory\Exception\ResultException;
use Ivory\Exception\StatementException;
use Ivory\Lang\SqlPattern\SqlPatternParser;
use Ivory\Query\SqlRelationRecipe;
use Ivory\Result\SqlState;
use Ivory\Result\SqlStateClass;

class StatementExecutionTest extends \Ivory\IvoryTestCase
{
    /** @var IStatementExecution */
    private $conn;

    protected function setUp()
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
    }


    public function testQuery()
    {
        $result = $this->conn->query('SELECT 42');
        $this->assertSame(1, $result->count());
        $this->assertSame(1, count($result->getColumns()));
        $this->assertSame(42, $result->value());

        $result = $this->conn->query('SELECT %int', 42);
        $this->assertSame(1, $result->count());
        $this->assertSame(1, count($result->getColumns()));
        $this->assertSame(42, $result->value());

        $result = $this->conn->query(
            "SELECT * FROM (VALUES (1, 'Ivory'), (42, 'wheee')) %ident (a, b)", 'tbl',
            'WHERE a = %int AND b = %s', 42, 'wheee'
        );
        $this->assertSame(1, $result->count());
        $this->assertSame(['a' => 42, 'b' => 'wheee'], $result->tuple()->toMap());

        $result = $this->conn->query(
            "SELECT * FROM (VALUES (1, 'Ivory'), (42, 'wheee')) %ident (a, b) WHERE a = %int:a AND b = %s:b",
            't',
            ['a' => 42, 'b' => 'wheee']
        );
        $this->assertSame(1, $result->count());
        $this->assertSame(['a' => 42, 'b' => 'wheee'], $result->tuple()->toMap());

        $patternParser = new SqlPatternParser();

        $pattern = $patternParser->parse('SELECT 42, %s');
        $result = $this->conn->query($pattern, 'wheee');
        $this->assertSame(1, $result->count());
        $this->assertSame([42, 'wheee'], $result->tuple()->toList());

        $pattern = $patternParser->parse('SELECT 42, %s:motto');
        $result = $this->conn->query($pattern, ['motto' => 'wheee']);
        $this->assertSame(1, $result->count());
        $this->assertSame([42, 'wheee'], $result->tuple()->toList());

        $recipe = SqlRelationRecipe::fromPattern('SELECT 42, %s:motto');
        $result = $this->conn->query($recipe, ['motto' => 'wheee']);
        $this->assertSame(1, $result->count());
        $this->assertSame([42, 'wheee'], $result->tuple()->toList());

        $result = @$this->conn->query("DO LANGUAGE plpgsql 'BEGIN END'");
        $this->assertEmpty($result->count());
        $this->assertEmpty($result->getColumns());

        try {
            $this->conn->query("DO LANGUAGE plpgsql 'BEGIN END'");
            $this->fail('A warning was expected due to command executed using query().');
        } catch (\PHPUnit_Framework_Error_Warning $warning) {
            $this->assertContains(
                'query', $warning->getMessage(),
                'The warning message should mention a query was expected', true
            );
            $this->assertContains(
                'command', $warning->getMessage(),
                'The warning message should mention command was actually executed', true
            );
        }
    }

    public function testQueryOneTuple()
    {
        $tuple = $this->conn->queryOneTuple(
            'SELECT MIN(num), MAX(num) FROM (VALUES (4), (-3), (3)) t (num)'
        );
        $this->assertSame([-3, 4], $tuple->toList());

        try {
            $this->conn->queryOneTuple('SELECT * FROM (VALUES (4), (-3), (3)) t (num)');
            $this->fail(ResultException::class . ' was expected - multiple tuple were returned from the database');
        } catch (ResultException $e) {
        }

        try {
            $this->conn->queryOneTuple('SELECT 1 WHERE FALSE');
            $this->fail(ResultException::class . ' was expected - no tuples were returned from the database');
        } catch (ResultException $e) {
        }
    }

    public function testCommand()
    {
        $result = $this->conn->command("DO LANGUAGE plpgsql 'BEGIN END'");
        $this->assertSame(0, $result->getAffectedRows());
        $this->assertSame('DO', $result->getCommandTag());

        $result = @$this->conn->command('VALUES (1),(2)');
        $this->assertSame(2, $result->getAffectedRows());
        $this->assertSame('SELECT 2', $result->getCommandTag());

        try {
            $this->conn->command('VALUES (1),(2)');
            $this->fail('A warning was expected due to query executed using command().');
        } catch (\PHPUnit_Framework_Error_Warning $warning) {
            $this->assertContains(
                'command', $warning->getMessage(),
                'The warning message should mention command was expected',
                true
            );
            $this->assertContains(
                'query', $warning->getMessage(),
                'The warning message should mention a query was actually executed',
                true
            );
        }
    }

    public function testQueryError()
    {
        try {
            $this->conn->query('SELECT log(-10)');
            $this->fail('Error expected');
        } catch (StatementException $e) {
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
        } catch (StatementExecutionTest__LogarithmException $e) {
        } finally {
            $stmtExFactory->clear();
        }

        $stmtExFactory->registerBySqlStateCode(
            SqlState::INVALID_ARGUMENT_FOR_LOGARITHM,
            StatementExecutionTest__LogarithmException::class
        );
        try {
            $this->conn->query('SELECT log(-10)');
            $this->fail('Error expected');
        } catch (StatementExecutionTest__LogarithmException $e) {
        } finally {
            $stmtExFactory->clear();
        }

        $stmtExFactory->registerBySqlStateClass(
            SqlStateClass::DATA_EXCEPTION,
            StatementExecutionTest__LogarithmException::class
        );
        try {
            $this->conn->query('SELECT log(-10)');
            $this->fail('Error expected');
        } catch (StatementExecutionTest__LogarithmException $e) {
        } finally {
            $stmtExFactory->clear();
        }
    }
}

class StatementExecutionTest__LogarithmException extends StatementException
{
}

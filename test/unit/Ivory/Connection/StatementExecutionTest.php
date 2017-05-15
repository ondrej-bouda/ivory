<?php
namespace Ivory\Connection;

use Ivory\Exception\ResultException;
use Ivory\Exception\StatementException;
use Ivory\Exception\UsageException;
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

        try {
            $this->conn->query("DO LANGUAGE plpgsql 'BEGIN END'");
            $this->fail(UsageException::class . ' is expected due to the mismatch of query() and command()');
        } catch (UsageException $e) {
        }
    }

    public function testQuerySingleTuple()
    {
        $tuple = $this->conn->querySingleTuple(
            'SELECT MIN(num), MAX(num) FROM (VALUES (4), (-3), (3)) t (num)'
        );
        $this->assertSame([-3, 4], $tuple->toList());

        $this->assertException(
            ResultException::class,
            function () {
                $this->conn->querySingleTuple('SELECT * FROM (VALUES (4), (-3), (3)) t (num)');
            },
            'multiple tuple were returned from the database'
        );

        $this->assertException(
            ResultException::class,
            function () {
                $this->conn->querySingleTuple('SELECT 1 WHERE FALSE');
            },
            'no tuples were returned from the database'
        );

        try {
            $this->conn->querySingleTuple("DO LANGUAGE plpgsql 'BEGIN END'");
            $this->fail(UsageException::class . ' is expected due to the mismatch of query() and command()');
        } catch (UsageException $e) {
        }
    }

    public function testQuerySingleValue()
    {
        $value = $this->conn->querySingleValue('SELECT 1');
        $this->assertSame(1, $value);

        try {
            $this->conn->querySingleValue('SELECT 1, 2');
            $this->fail(ResultException::class . ' was expected - multiple columns were returned from the database');
        } catch (ResultException $e) {
        }

        try {
            $this->conn->querySingleValue('SELECT');
            $this->fail(ResultException::class . ' was expected - no columns were returned from the database');
        } catch (ResultException $e) {
        }

        try {
            $this->conn->querySingleValue('SELECT 1 UNION SELECT 2');
            $this->fail(ResultException::class . ' was expected - multiple rows were returned from the database');
        } catch (ResultException $e) {
        }

        try {
            $this->conn->querySingleValue('SELECT 1 WHERE FALSE');
            $this->fail(ResultException::class . ' was expected - no rows were returned from the database');
        } catch (ResultException $e) {
        }

        try {
            $this->conn->querySingleValue("DO LANGUAGE plpgsql 'BEGIN END'");
            $this->fail(UsageException::class . ' is expected due to the mismatch of query() and command()');
        } catch (UsageException $e) {
        }
    }

    public function testCommand()
    {
        $result = $this->conn->command("DO LANGUAGE plpgsql 'BEGIN END'");
        $this->assertSame(0, $result->getAffectedRows());
        $this->assertSame('DO', $result->getCommandTag());

        try {
            $this->conn->command('VALUES (1),(2)');
            $this->fail(UsageException::class . ' is expected due to the mismatch of query() and command()');
        } catch (UsageException $e) {
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

    public function testCustomExceptionByMessage()
    {
        $stmtExFactory = $this->conn->getStatementExceptionFactory();
        $stmtExFactory->registerByMessage('~logarithm~', StatementExecutionTest__LogarithmException::class);

        $this->expectException(StatementExecutionTest__LogarithmException::class);
        $this->conn->query('SELECT log(-10)');
    }

    public function testCustomExceptionBySqlStateCode()
    {
        $stmtExFactory = $this->conn->getStatementExceptionFactory();
        $stmtExFactory->registerBySqlStateCode(
            SqlState::INVALID_ARGUMENT_FOR_LOGARITHM,
            StatementExecutionTest__LogarithmException::class
        );

        $this->expectException(StatementExecutionTest__LogarithmException::class);
        $this->conn->query('SELECT log(-10)');
    }

    public function testCustomExceptionBySqlStateClass()
    {
        $stmtExFactory = $this->conn->getStatementExceptionFactory();
        $stmtExFactory->registerBySqlStateClass(
            SqlStateClass::DATA_EXCEPTION,
            StatementExecutionTest__LogarithmException::class
        );

        $this->expectException(StatementExecutionTest__LogarithmException::class);
        $this->conn->query('SELECT log(-10)');
    }
}

class StatementExecutionTest__LogarithmException extends StatementException
{
}

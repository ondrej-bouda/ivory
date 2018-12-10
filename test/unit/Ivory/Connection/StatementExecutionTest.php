<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Exception\ResultDimensionException;
use Ivory\Exception\StatementException;
use Ivory\Exception\UsageException;
use Ivory\IvoryTestCase;
use Ivory\Lang\SqlPattern\SqlPatternParser;
use Ivory\Query\SqlCommand;
use Ivory\Query\SqlRelationDefinition;
use Ivory\Result\ICommandResult;
use Ivory\Result\IQueryResult;
use Ivory\Lang\Sql\SqlState;
use Ivory\Lang\Sql\SqlStateClass;

class StatementExecutionTest extends IvoryTestCase
{
    /** @var IConnection */
    private $conn;

    protected function setUp(): void
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

        $relDef = SqlRelationDefinition::fromPattern('SELECT 42, %s:motto');
        $result = $this->conn->query($relDef, ['motto' => 'wheee']);
        $this->assertSame(1, $result->count());
        $this->assertSame([42, 'wheee'], $result->tuple()->toList());

        $this->assertException(
            UsageException::class,
            function () {
                $this->conn->query("DO LANGUAGE plpgsql 'BEGIN END'");
            },
            UsageException::class . ' is expected due to the mismatch of query() and command()'
        );
    }

    public function testQuerySingleTuple()
    {
        $tuple = $this->conn->querySingleTuple(
            'SELECT MIN(num), MAX(num) FROM (VALUES (4), (-3), (3)) t (num)'
        );
        $this->assertSame([-3, 4], $tuple->toList());

        $this->assertException(
            ResultDimensionException::class,
            function () {
                $this->conn->querySingleTuple('SELECT * FROM (VALUES (4), (-3), (3)) t (num)');
            },
            'multiple tuple were returned from the database'
        );

        $this->assertException(
            ResultDimensionException::class,
            function () {
                $this->conn->querySingleTuple('SELECT 1 WHERE FALSE');
            },
            'no tuples were returned from the database'
        );

        $this->assertException(
            UsageException::class,
            function () {
                $this->conn->querySingleTuple("DO LANGUAGE plpgsql 'BEGIN END'");
            },
            UsageException::class . ' is expected due to the mismatch of query() and command()'
        );
    }

    public function testQuerySingleColumn()
    {
        $col = $this->conn->querySingleColumn(
            'SELECT num FROM (VALUES (4), (-3), (3)) t (num)'
        );
        $this->assertSame([4, -3, 3], $col->toArray());

        $this->assertException(
            ResultDimensionException::class,
            function () {
                $this->conn->querySingleColumn('SELECT 1, 2');
            },
            'multiple columns were returned from the database'
        );

        $this->assertException(
            ResultDimensionException::class,
            function () {
                $this->conn->querySingleColumn('SELECT FROM (VALUES (4), (-3), (3)) t (num)');
            },
            'no columns were returned from the database'
        );

        $this->assertException(
            UsageException::class,
            function () {
                $this->conn->querySingleColumn("DO LANGUAGE plpgsql 'BEGIN END'");
            },
            UsageException::class . ' is expected due to the mismatch of query() and command()'
        );
    }

    public function testQuerySingleValue()
    {
        $value = $this->conn->querySingleValue('SELECT 1');
        $this->assertSame(1, $value);
        
        $this->assertException(
            ResultDimensionException::class,
            function () {
                $this->conn->querySingleValue('SELECT 1, 2');
            },
            'multiple columns were returned from the database'
        );

        $this->assertException(
            ResultDimensionException::class,
            function () {
                $this->conn->querySingleValue('SELECT');
            },
            'no columns were returned from the database'
        );

        $this->assertException(
            ResultDimensionException::class,
            function () {
                $this->conn->querySingleValue('SELECT 1 UNION SELECT 2');
            },
            'multiple rows were returned from the database'
        );

        $this->assertException(
            ResultDimensionException::class,
            function () {
                $this->conn->querySingleValue('SELECT 1 WHERE FALSE');
            },
            'no rows were returned from the database'
        );

        $this->assertException(
            UsageException::class,
            function () {
                $this->conn->querySingleValue("DO LANGUAGE plpgsql 'BEGIN END'");
            },
            'mismatch of query() and command()'
        );
    }

    public function testCommand()
    {
        $result = $this->conn->command("DO LANGUAGE plpgsql 'BEGIN END'");
        $this->assertSame(0, $result->getAffectedRows());
        $this->assertSame('DO', $result->getCommandTag());

        $this->assertException(
            UsageException::class,
            function () {
                $this->conn->command('VALUES (1),(2)');
            },
            'mismatch of query() and command()'
        );
    }

    public function testQueryError()
    {
        try {
            $this->conn->query('SELECT log(-10)');
            $this->fail(StatementException::class . ' expected');
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

    public function testUnsatisfiedQueryParameters()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Values for parameters "a", "b" and "c" have not been set.');
        $this->conn->query(
            'SELECT %:a + %:b + %:c + %:d',
            ['d' => 1]
        );
    }

    public function testUnsatisfiedCommandParameters()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Values for parameters "a", "b" and "c" have not been set.');
        $this->conn->command(
            'INSERT INTO t (x) VALUES (%:a + %:b + %:c + %:d)',
            ['d' => 1]
        );
    }

    public function testQueryArgumentPrecedence()
    {
        $relDef = SqlRelationDefinition::fromPattern('SELECT %:a + %:b + %:c');
        $relDef->setParams(['a' => 1, 'b' => 2]);

        $this->assertSame(10, $this->conn->querySingleValue($relDef, ['b' => 3, 'c' => 6]));

        $this->assertSame(9, $this->conn->querySingleValue($relDef, ['c' => 6]));
    }

    public function testCommandArgumentPrecedence()
    {
        $tx = $this->conn->startTransaction();

        try {
            $this->conn->command('CREATE TEMPORARY TABLE t (x INT) ON COMMIT DROP');

            $cmd = SqlCommand::fromPattern('INSERT INTO t (x) VALUES (%:a), (%:b), (%:c)');
            $cmd->setParams(['a' => 1, 'b' => 2]);

            $this->conn->command($cmd, ['b' => 3, 'c' => 6]);
            $this->assertSame(10, $this->conn->querySingleValue('SELECT SUM(x) FROM t'));

            $this->conn->command('TRUNCATE t');

            $this->conn->command($cmd, ['c' => 6]);
            $this->assertSame(9, $this->conn->querySingleValue('SELECT SUM(x) FROM t'));
        } finally {
            $tx->rollback();
        }
    }

    public function testExecuteStatement()
    {
        $tx = $this->conn->startTransaction();

        try {
            $result = $this->conn->executeStatement('CREATE TABLE t (a INT)');
            $this->assertInstanceOf(ICommandResult::class, $result);
            assert($result instanceof ICommandResult);
            $this->assertSame('CREATE TABLE', $result->getCommandTag());

            $relDef = SqlRelationDefinition::fromSql('SELECT 1');
            $result2 = $this->conn->executeStatement($relDef);
            $this->assertInstanceOf(IQueryResult::class, $result2);
            assert($result2 instanceof IQueryResult);
            $this->assertSame(1, $result2->value());
        } finally {
            $tx->rollback();
        }
    }

    public function testRunScript()
    {
        $tx = $this->conn->startTransaction();

        try {
            $results = $this->conn->runScript(
                'CREATE TABLE t (a INT);
                 INSERT INTO t (a) VALUES (1), (2);
                 SELECT * FROM t'
            );
            $this->assertEquals(3, count($results));

            $this->assertInstanceOf(ICommandResult::class, $results[0]);
            $this->assertSame('CREATE TABLE', $results[0]->getCommandTag());
            assert($results[0] instanceof ICommandResult);
            $this->assertSame(0, $results[0]->getAffectedRows());

            $this->assertInstanceOf(ICommandResult::class, $results[1]);
            $this->assertSame('INSERT 0 2', $results[1]->getCommandTag());
            assert($results[1] instanceof ICommandResult);
            $this->assertSame(2, $results[1]->getAffectedRows());

            $this->assertInstanceOf(IQueryResult::class, $results[2]);
            $this->assertSame('SELECT 2', $results[2]->getCommandTag());
            assert($results[2] instanceof IQueryResult);
            $this->assertSame([1, 2], $results[2]->col('a')->toArray());
        } finally {
            $tx->rollback();
        }
    }

    public function testStatementException()
    {
        try {
            $this->conn->query("SELECT\nfoo");
            $this->fail(StatementException::class . ' expected');
        } catch (StatementException $e) {
            $this->assertSame("SELECT\nfoo", $e->getQuery());
            $this->assertEquals(SqlState::fromCode(SqlState::UNDEFINED_COLUMN), $e->getSqlState());
            $this->assertSame(SqlState::UNDEFINED_COLUMN, $e->getSqlStateCode());
            $this->assertSame(8, $e->getStatementPosition());
            $this->assertNull($e->getContext());
            $this->assertNull($e->getInternalPosition());
            $this->assertNull($e->getInternalQuery());
            if (PHP_VERSION_ID >= 70300) {
                $this->assertSame('ERROR', $e->getNonlocalizedSeverity());
            }
        }

        $tx = $this->conn->startTransaction();
        try {
            $this->conn->command("SET lc_messages TO 'en_US.UTF-8'");
            $this->conn->command(<<<'SQL'
CREATE FUNCTION foo() RETURNS INT AS $$
BEGIN
    RETURN
        bar;
END;
$$ LANGUAGE plpgsql
SQL
);
            try {
                $this->conn->query('SELECT foo()');
                $this->fail(StatementException::class . ' expected');
            } catch (StatementException $e) {
                $this->assertSame('column "bar" does not exist', $e->getMessage());
                $this->assertSame('SELECT foo()', $e->getQuery());
                $this->assertEquals(SqlState::fromCode(SqlState::UNDEFINED_COLUMN), $e->getSqlState());
                $this->assertSame(SqlState::UNDEFINED_COLUMN, $e->getSqlStateCode());
                $this->assertNull($e->getStatementPosition());
                $this->assertSame('PL/pgSQL function foo() line 3 at RETURN', $e->getContext());
                $this->assertSame(8, $e->getInternalPosition());
                $this->assertSame('SELECT bar', $e->getInternalQuery());
                if (PHP_VERSION_ID >= 70300) {
                    $this->assertSame('ERROR', $e->getNonlocalizedSeverity());
                }
            }
        } finally {
            $tx->rollback();
        }
    }
}

class StatementExecutionTest__LogarithmException extends StatementException
{
}

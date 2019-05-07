<?php
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Exception\ClosedCursorException;
use Ivory\Exception\StatementException;
use Ivory\IvoryTestCase;
use Ivory\Lang\Sql\SqlState;
use Ivory\Query\SqlRelationDefinition;

class CursorTest extends IvoryTestCase
{
    public function testCursorControl()
    {
        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $conn->declareCursor('y', SqlRelationDefinition::fromSql('SELECT 1 UNION SELECT 2'));
            $conn->declareCursor('x', SqlRelationDefinition::fromSql("SELECT 'foo'"));
            $conn->declareCursor('z', SqlRelationDefinition::fromSql('SELECT FALSE'), ICursor::BINARY);

            $allCursors = $conn->getAllCursors();
            self::assertSame(['y', 'x', 'z'], array_keys($allCursors));
            self::assertTrue($allCursors['z']->getProperties()->isBinary());
            self::assertFalse($allCursors['x']->isClosed());
            self::assertFalse($allCursors['y']->isClosed());
            self::assertFalse($allCursors['z']->isClosed());

            $conn->closeAllCursors();
            self::assertSame([], $conn->getAllCursors());
            self::assertTrue($allCursors['x']->isClosed());
            self::assertTrue($allCursors['y']->isClosed());
            self::assertTrue($allCursors['z']->isClosed());
        } finally {
            $tx->rollback();
        }
    }

    public function testTypeConverter()
    {
        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $conn->command(
                <<<'SQL'
                CREATE FUNCTION get_cur() RETURNS refcursor AS $$
                DECLARE
                    cur refcursor;
                BEGIN
                    OPEN cur FOR SELECT 1 UNION SELECT 2;
                    RETURN cur;
                END;
                $$ LANGUAGE plpgsql
SQL
            );
            $cur = $conn->querySingleValue('SELECT get_cur()');
            self::assertInstanceOf(ICursor::class, $cur);
            assert($cur instanceof ICursor);

            $cur2 = $conn->querySingleValue('SELECT %refcursor', $cur->getName());
            self::assertInstanceOf(ICursor::class, $cur2);
            assert($cur2 instanceof ICursor);

            $cur3 = $conn->querySingleValue('SELECT %', $cur);
            self::assertInstanceOf(ICursor::class, $cur3);
            assert($cur3 instanceof ICursor);

            self::assertFalse($cur->isClosed());
            self::assertFalse($cur2->isClosed());
            self::assertFalse($cur3->isClosed());

            $cur2->close();
            self::assertTrue($cur->isClosed());
            self::assertTrue($cur2->isClosed());
            self::assertTrue($cur3->isClosed());
        } finally {
            $tx->rollback();
        }
    }

    /**
     * @depends testCursorControl
     */
    public function testCursorProperties()
    {
        $conn = $this->getIvoryConnection();

        $tx = $conn->startTransaction();
        try {
            $cur1 = $conn->declareCursor('c1', SqlRelationDefinition::fromSql('VALUES (1), (2)'));
            self::assertSame('c1', $cur1->getName());
            self::assertTrue($cur1->getProperties()->isScrollable());
            self::assertFalse($cur1->getProperties()->isHoldable());
            self::assertFalse($cur1->getProperties()->isBinary());
            self::assertFalse($cur1->isClosed());
            $tx->rollback();
            self::assertTrue($cur1->isClosed());
        } finally {
            $tx->rollbackIfOpen();
        }

        $tx = $conn->startTransaction();
        try {
            $cur2 = $conn->declareCursor(
                'c2',
                SqlRelationDefinition::fromSql('SELECT 1'),
                ICursor::BINARY | ICursor::HOLDABLE | ICursor::NON_SCROLLABLE
            );
            self::assertFalse($cur2->getProperties()->isScrollable());
            self::assertTrue($cur2->getProperties()->isHoldable());
            self::assertTrue($cur2->getProperties()->isBinary());
            self::assertFalse($cur2->isClosed());

            $cur2a = $conn->getAllCursors()['c2'];
            self::assertFalse($cur2a->getProperties()->isScrollable());
            self::assertTrue($cur2a->getProperties()->isHoldable());
            self::assertTrue($cur2a->getProperties()->isBinary());
            self::assertFalse($cur2a->isClosed());

            $tx->commit();
            self::assertFalse($cur2->isClosed());
            self::assertFalse($cur2a->isClosed());

            $cur2->close();
            self::assertTrue($cur2->isClosed());
            self::assertTrue($cur2a->isClosed());
        } finally {
            $tx->rollbackIfOpen();
        }

        $tx = $conn->startTransaction();
        try {
            $cur3 = $conn->declareCursor(
                'c3',
                SqlRelationDefinition::fromSql('VALUES (1), (2)'),
                ICursor::SCROLLABLE
            );
            self::assertTrue($cur3->getProperties()->isScrollable());
            self::assertFalse($cur3->getProperties()->isHoldable());
            self::assertFalse($cur3->getProperties()->isBinary());
            self::assertFalse($cur3->isClosed());

            $cur3a = $conn->getAllCursors()['c3'];
            self::assertTrue($cur3a->getProperties()->isScrollable());
            self::assertFalse($cur3a->getProperties()->isHoldable());
            self::assertFalse($cur3a->getProperties()->isBinary());
            self::assertFalse($cur3a->isClosed());

            $tx->commit();
            self::assertTrue($cur3->isClosed());
            self::assertTrue($cur3a->isClosed());
        } finally {
            $tx->rollbackIfOpen();
        }

        $tx = $conn->startTransaction();
        try {
            $conn->command(
                <<<'SQL'
                CREATE FUNCTION get_cur() RETURNS refcursor AS $$
                DECLARE
                    cur SCROLL CURSOR FOR SELECT 1 UNION SELECT 2;
                BEGIN
                    OPEN cur;
                    RETURN cur;
                END;
                $$ LANGUAGE plpgsql
SQL
            );
            $cur4 = $conn->querySingleValue('SELECT get_cur()');
            assert($cur4 instanceof ICursor);
            self::assertFalse($cur4->isClosed());
            self::assertTrue($cur4->getProperties()->isScrollable());
            self::assertFalse($cur4->getProperties()->isHoldable());
            self::assertFalse($cur4->getProperties()->isBinary());
        } finally {
            $tx->rollback();
        }
    }

    /**
     * @depends testCursorControl
     */
    public function testFetch()
    {
        $relDef = SqlRelationDefinition::fromSql("VALUES (1, 'a'), (2, 'b'), (3, 'c'), (4, 'd'), (5, 'e')");

        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $cur = $conn->declareCursor('cur', $relDef, ICursor::SCROLLABLE);
            self::assertNull($cur->fetch(0), 'stay before the first row');
            self::assertSame('a', $cur->fetch()->value(1), 'move to the first row');
            self::assertSame('e', $cur->fetch(4)->value(1), 'move to the fifth = last row');
            self::assertNull($cur->fetch(1), 'move after the last row');
            self::assertNull($cur->fetch(1), 'stay after the last row');
            self::assertNull($cur->fetch(0), 'stay after the last row');
            self::assertSame('e', $cur->fetch(-1)->value(1), 'move to the fifth row');
            self::assertNull($cur->fetch(-10), 'move before the first row');
            self::assertSame('b', $cur->fetch(2)->value(1), 'move to the second row');
            self::assertSame('b', $cur->fetch(0)->value(1), 'stay at the second row');
            self::assertSame('d', $cur->fetch(2)->value(1), 'move to the fourth row');
            self::assertNull($cur->fetch(2), 'move after the last row');
            $cur->close();
            self::assertException(
                ClosedCursorException::class,
                function () use ($cur) {
                    $cur->fetch(-2);
                }
            );


            $noScrollCur = $conn->declareCursor('no-scroll-cur', $relDef, ICursor::NON_SCROLLABLE);
            self::assertSame('a', $noScrollCur->fetch(1)->value(1));
            self::assertSame('b', $noScrollCur->fetch()->value(1));
            self::assertSame('d', $noScrollCur->fetch(2)->value(1));

            $tx->savepoint('s');
            try {
                $noScrollCur->fetch(0);
                self::fail(StatementException::class . ' expected');
            } catch (StatementException $e) {
                self::assertSame(SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE, $e->getSqlStateCode());
                $tx->rollbackToSavepoint('s');
            }

            try {
                $noScrollCur->fetch(-1);
                self::fail(StatementException::class . ' expected');
            } catch (StatementException $e) {
                self::assertSame(SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE, $e->getSqlStateCode());
                $tx->rollbackToSavepoint('s');
            }


            $binaryCur = $conn->declareCursor('binary-cur', $relDef, ICursor::BINARY | ICursor::SCROLLABLE);
            self::assertTrue($binaryCur->getProperties()->isBinary());
            self::assertTrue($conn->getAllCursors()['binary-cur']->getProperties()->isBinary());
            self::assertSame('a', $binaryCur->fetch()->value(1));
            self::assertSame('c', $binaryCur->fetch(2)->value(1));
            self::assertSame('c', $binaryCur->fetch(0)->value(1));
            self::assertSame('a', $binaryCur->fetch(-2)->value(1));
        } finally {
            $tx->rollback();
        }
    }

    /**
     * @depends testCursorControl
     */
    public function testFetchAt()
    {
        $relDef = SqlRelationDefinition::fromSql("VALUES (1, 'a'), (2, 'b'), (3, 'c'), (4, 'd'), (5, 'e')");

        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $cur = $conn->declareCursor('cur', $relDef, ICursor::SCROLLABLE);
            self::assertSame('c', $cur->fetchAt(3)->value(1));
            self::assertSame('a', $cur->fetchAt(1)->value(1));
            self::assertNull($cur->fetchAt(0));
            self::assertSame('a', $cur->fetchAt(-5)->value(1));
            self::assertNull($cur->fetchAt(-6));
            self::assertSame('e', $cur->fetchAt(5)->value(1));
            self::assertNull($cur->fetchAt(6));
            $cur->close();
            self::assertException(
                ClosedCursorException::class,
                function () use ($cur) {
                    $cur->fetchAt(-2);
                }
            );


            $noScrollCur = $conn->declareCursor('no-scroll-cur', $relDef, ICursor::NON_SCROLLABLE);
            self::assertSame('a', $noScrollCur->fetchAt(1)->value(1));
            self::assertSame('c', $noScrollCur->fetchAt(3)->value(1));
            $tx->savepoint('s');
            try {
                $noScrollCur->fetchAt(-1);
                self::fail(StatementException::class . ' expected');
            } catch (StatementException $e) {
                self::assertSame(SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE, $e->getSqlStateCode());
                $tx->rollbackToSavepoint('s');
            }

            try {
                $noScrollCur->fetchAt(0);
                self::fail(StatementException::class . ' expected');
            } catch (StatementException $e) {
                self::assertSame(SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE, $e->getSqlStateCode());
                $tx->rollbackToSavepoint('s');
            }


            $binaryCur = $conn->declareCursor('binary-cur', $relDef, ICursor::BINARY | ICursor::SCROLLABLE);
            self::assertTrue($binaryCur->getProperties()->isBinary());
            self::assertTrue($conn->getAllCursors()['binary-cur']->getProperties()->isBinary());
            self::assertSame('a', $binaryCur->fetchAt(1)->value(1));
            self::assertNull($binaryCur->fetchAt(0));
            self::assertSame('d', $binaryCur->fetchAt(-2)->value(1));
        } finally {
            $tx->rollback();
        }
    }

    /**
     * @depends testCursorControl
     */
    public function testFetchMulti()
    {
        $relDef = SqlRelationDefinition::fromSql("VALUES (1, 'a'), (2, 'b'), (3, 'c'), (4, 'd'), (5, 'e')");

        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $cur = $conn->declareCursor('cur', $relDef, ICursor::SCROLLABLE);
            self::assertSame([], $cur->fetchMulti(0), 'stay before the first row');
            $res = $cur->fetchMulti(1);
            self::assertTupleVals(['a'], $res, 1, 'move to the first row');
            self::assertTupleVals(['b', 'c', 'd', 'e'], $cur->fetchMulti(4), 1, 'move to the fifth row');
            self::assertSame([], $cur->fetchMulti(1), 'move after the last row');
            self::assertSame([], $cur->fetchMulti(1), 'stay after the last row');
            self::assertSame([], $cur->fetchMulti(0), 'stay after the last row');
            self::assertTupleVals(['e'], $cur->fetchMulti(-1), 1, 'move to the fifth row');
            self::assertTupleVals(['d', 'c', 'b', 'a'], $cur->fetchMulti(-10), 1, 'move before the first row');
            self::assertTupleVals(['a', 'b'], $cur->fetchMulti(2), 1, 'move to the second row');
            self::assertTupleVals(['b'], $cur->fetchMulti(0), 1, 'stay at the second row');
            self::assertTupleVals(
                ['c', 'd', 'e'],
                $cur->fetchMulti(ICursor::ALL_REMAINING),
                1,
                'move after the last row'
            );
            self::assertTupleVals(
                ['e', 'd', 'c', 'b', 'a'],
                $cur->fetchMulti(ICursor::ALL_FOREGOING),
                1,
                'move before the first row'
            );
            $cur->close();
            self::assertException(
                ClosedCursorException::class,
                function () use ($cur) {
                    $cur->fetchMulti(2);
                }
            );


            $noScrollCur = $conn->declareCursor('no-scroll-cur', $relDef, ICursor::NON_SCROLLABLE);

            $tuples = $noScrollCur->fetchMulti(1);
            self::assertCount(1, $tuples);
            self::assertInstanceOf(ITuple::class, $tuples[0]);
            self::assertSame('a', $tuples[0]->value(1));

            self::assertTupleVals(['b', 'c', 'd'], $noScrollCur->fetchMulti(3), 1);

            $tx->savepoint('s');

            try {
                $noScrollCur->fetchMulti(0);
                self::fail(StatementException::class . ' expected');
            } catch (StatementException $e) {
                self::assertSame(SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE, $e->getSqlStateCode());
                $tx->rollbackToSavepoint('s');
            }

            try {
                $noScrollCur->fetchMulti(-1);
                self::fail(StatementException::class . ' expected');
            } catch (StatementException $e) {
                self::assertSame(SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE, $e->getSqlStateCode());
                $tx->rollbackToSavepoint('s');
            }


            $binaryCur = $conn->declareCursor('binary-cur', $relDef, ICursor::BINARY | ICursor::SCROLLABLE);
            self::assertTrue($binaryCur->getProperties()->isBinary());
            self::assertTrue($conn->getAllCursors()['binary-cur']->getProperties()->isBinary());
            self::assertSame('a', $binaryCur->fetchMulti(1)[0]->value(1));
            self::assertSame('c', $binaryCur->fetchMulti(2)[1]->value(1));
            self::assertSame('c', $binaryCur->fetchMulti(0)[0]->value(1));
            self::assertSame('a', $binaryCur->fetchMulti(-2)[1]->value(1));
        } finally {
            $tx->rollback();
        }
    }

    /**
     * @depends testFetch
     */
    public function testMoveBy()
    {
        $relDef = SqlRelationDefinition::fromSql("VALUES (1, 'a'), (2, 'b'), (3, 'c'), (4, 'd'), (5, 'e')");

        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $cur = $conn->declareCursor('cur', $relDef, ICursor::SCROLLABLE);

            self::assertSame(0, $cur->moveBy(0), 'stay before the first row');
            self::assertNull($cur->fetch(0));

            self::assertSame(1, $cur->moveBy(1), 'move to the first row');
            self::assertSame('a', $cur->fetch(0)->value(1));

            self::assertSame(1, $cur->moveBy(4), 'move to the fifth = last row');
            self::assertSame('e', $cur->fetch(0)->value(1));

            self::assertSame(0, $cur->moveBy(1), 'move after the last row');
            self::assertNull($cur->fetch(0));

            self::assertSame(0, $cur->moveBy(1), 'stay after the last row');
            self::assertNull($cur->fetch(0));

            self::assertSame(0, $cur->moveBy(0), 'stay after the last row');
            self::assertNull($cur->fetch(0));

            self::assertSame(1, $cur->moveBy(-1), 'move to the fifth row');
            self::assertSame('e', $cur->fetch(0)->value(1));

            self::assertSame(0, $cur->moveBy(-10), 'move before the first row');
            self::assertNull($cur->fetch(0));

            self::assertSame(1, $cur->moveBy(2), 'move to the second row');
            self::assertSame('b', $cur->fetch(0)->value(1));

            self::assertSame(1, $cur->moveBy(0), 'stay at the second row');
            self::assertSame('b', $cur->fetch(0)->value(1));

            self::assertSame(1, $cur->moveBy(2), 'move to the fourth row');
            self::assertSame('d', $cur->fetch(0)->value(1));

            self::assertSame(0, $cur->moveBy(2), 'move after the last row');
            self::assertNull($cur->fetch(0));

            $cur->close();
            self::assertException(
                ClosedCursorException::class,
                function () use ($cur) {
                    $cur->moveBy(-2);
                }
            );


            $noScrollCur = $conn->declareCursor('no-scroll-cur', $relDef, ICursor::NON_SCROLLABLE);

            self::assertSame(1, $noScrollCur->moveBy(1));
            self::assertSame(1, $noScrollCur->moveBy(1));
            self::assertSame(1, $noScrollCur->moveBy(0));
            self::assertSame(1, $noScrollCur->moveBy(2));
            $tx->savepoint('s');
            try {
                $noScrollCur->moveBy(-1);
                self::fail(StatementException::class . ' expected');
            } catch (StatementException $e) {
                self::assertSame(SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE, $e->getSqlStateCode());
                $tx->rollbackToSavepoint('s');
            }
        } finally {
            $tx->rollback();
        }
    }

    /**
     * @depends testCursorControl
     */
    public function testMoveAndCount()
    {
        $relDef = SqlRelationDefinition::fromSql("VALUES (1, 'a'), (2, 'b'), (3, 'c'), (4, 'd'), (5, 'e')");

        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $cur = $conn->declareCursor('cur', $relDef, ICursor::SCROLLABLE);

            self::assertSame(0, $cur->moveAndCount(0), 'stay before the first row');
            self::assertSame(1, $cur->moveAndCount(1), 'move to the first row');
            self::assertSame(4, $cur->moveAndCount(4), 'move to the fifth = last row');
            self::assertSame(0, $cur->moveAndCount(1), 'move after the last row');
            self::assertSame(0, $cur->moveAndCount(1), 'stay after the last row');
            self::assertSame(0, $cur->moveAndCount(0), 'stay after the last row');
            self::assertSame(1, $cur->moveAndCount(-1), 'move to the fifth row');
            self::assertSame(4, $cur->moveAndCount(-10), 'move before the first row');
            self::assertSame(2, $cur->moveAndCount(2), 'move to the second row');
            self::assertSame(1, $cur->moveAndCount(0), 'stay at the second row');
            self::assertSame(3, $cur->moveAndCount(ICursor::ALL_REMAINING), 'move after the last row');
            self::assertSame(5, $cur->moveAndCount(ICursor::ALL_FOREGOING), 'move before the first row');
            $cur->close();
            self::assertException(
                ClosedCursorException::class,
                function () use ($cur) {
                    $cur->moveAndCount(-2);
                }
            );

            $noScrollCur = $conn->declareCursor('no-scroll-cur', $relDef, ICursor::NON_SCROLLABLE);
            self::assertSame(1, $noScrollCur->moveAndCount(1));
            self::assertSame(1, $noScrollCur->moveAndCount(0));
            self::assertSame(3, $noScrollCur->moveAndCount(3));
            $tx->savepoint('s');
            try {
                $noScrollCur->moveAndCount(-1);
                self::fail(StatementException::class . ' expected');
            } catch (StatementException $e) {
                self::assertSame(SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE, $e->getSqlStateCode());
                $tx->rollbackToSavepoint('s');
            }
        } finally {
            $tx->rollback();
        }
    }

    /**
     * @depends testFetch
     */
    public function testMoveTo()
    {
        $relDef = SqlRelationDefinition::fromSql("VALUES (1, 'a'), (2, 'b'), (3, 'c'), (4, 'd'), (5, 'e')");

        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $cur = $conn->declareCursor('cur', $relDef, ICursor::SCROLLABLE);

            self::assertSame(1, $cur->moveTo(3));
            self::assertSame('c', $cur->fetch(0)->value(1));

            self::assertSame(1, $cur->moveTo(1));
            self::assertSame('a', $cur->fetch(0)->value(1));

            self::assertSame(0, $cur->moveTo(0));
            self::assertNull($cur->fetch(0));

            self::assertSame(1, $cur->moveTo(-5));
            self::assertSame('a', $cur->fetch(0)->value(1));

            self::assertSame(0, $cur->moveTo(-6));
            self::assertNull($cur->fetch(0));

            self::assertSame(1, $cur->moveTo(5));
            self::assertSame('e', $cur->fetch(0)->value(1));

            self::assertSame(0, $cur->moveTo(6));
            self::assertNull($cur->fetch(0));

            $cur->close();
            self::assertException(
                ClosedCursorException::class,
                function () use ($cur) {
                    $cur->moveTo(-2);
                }
            );


            $noScrollCur = $conn->declareCursor('no-scroll-cur', $relDef, ICursor::NON_SCROLLABLE);
            self::assertSame(1, $noScrollCur->moveTo(1));
            self::assertSame(1, $noScrollCur->moveTo(3));
            $tx->savepoint('s');
            try {
                $noScrollCur->moveTo(-1);
                self::fail(StatementException::class . ' expected');
            } catch (StatementException $e) {
                self::assertSame(SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE, $e->getSqlStateCode());
                $tx->rollbackToSavepoint('s');
            }

            try {
                $noScrollCur->moveTo(0);
                self::fail(StatementException::class . ' expected');
            } catch (StatementException $e) {
                self::assertSame(SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE, $e->getSqlStateCode());
                $tx->rollbackToSavepoint('s');
            }
        } finally {
            $tx->rollback();
        }
    }

    /**
     * @depends testFetch
     */
    public function testCursorTraversable()
    {
        $relDef = SqlRelationDefinition::fromSql("VALUES (1, 'a'), (2, 'b'), (3, 'c'), (4, 'd'), (5, 'e')");

        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $cur = $conn->declareCursor('no-scroll-cur', $relDef, ICursor::NON_SCROLLABLE);

            $actualValues = [];
            foreach ($cur as $key => $tuple) {
                assert($tuple instanceof ITuple);
                $actualValues[$key] = $tuple->value(1);
            }
            self::assertSame([1 => 'a', 'b', 'c', 'd', 'e'], $actualValues);

            $actualValuesAgain = [];
            foreach ($cur as $key => $tuple) {
                assert($tuple instanceof ITuple);
                $actualValuesAgain[$key] = $tuple->value(1);
            }
            self::assertSame([1 => 'a', 'b', 'c', 'd', 'e'], $actualValuesAgain);
        } finally {
            $tx->rollback();
        }
    }

    /**
     * @depends testCursorTraversable
     */
    public function testBufferedIterator()
    {
        $relDef = SqlRelationDefinition::fromSql("VALUES (1, 'a'), (2, 'b'), (3, 'c'), (4, 'd'), (5, 'e')");

        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $cur = $conn->declareCursor('no-scroll-cur', $relDef, ICursor::NON_SCROLLABLE);

            $bufSizes = [0, 1, 2, 4, 5, 10];
            foreach ($bufSizes as $bufSize) {
                $actualValues = [];
                foreach ($cur->getIterator($bufSize) as $key => $tuple) {
                    assert($tuple instanceof ITuple);
                    $actualValues[$key] = $tuple->value(1);
                }
                self::assertSame([1 => 'a', 'b', 'c', 'd', 'e'], $actualValues, "Buffer size $bufSize");

                $actualValuesAgain = [];
                foreach ($cur->getIterator($bufSize) as $key => $tuple) {
                    assert($tuple instanceof ITuple);
                    $actualValuesAgain[$key] = $tuple->value(1);
                }
                self::assertSame([1 => 'a', 'b', 'c', 'd', 'e'], $actualValuesAgain, "Buffer size $bufSize");
            }
        } finally {
            $tx->rollback();
        }
    }
}

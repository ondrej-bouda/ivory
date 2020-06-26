<?php
/** @noinspection PhpInappropriateInheritDocUsageInspection PhpStorm bug WI-54015 */
declare(strict_types=1);
namespace Ivory\Relation;

use Ivory\Connection\IStatementExecution;
use Ivory\Exception\ClosedCursorException;
use Ivory\Query\SqlRelationDefinition;

/**
 * Handle for controlling a cursor.
 *
 * {@inheritDoc}
 *
 * Note the implementation checks its local _closed_ flag not only to save queries to the database, but also to prevent
 * from one `Cursor` object being used repeatedly for another, same-named cursor later.
 */
class Cursor implements ICursor
{
    private $stmtExec;
    private $name;
    private $properties;
    private $closed = null;

    /**
     * @param IStatementExecution $stmtExec
     * @param string $name
     * @param CursorProperties|null $properties known characteristics of the cursor, or <tt>null</tt> if unknown
     */
    public function __construct(IStatementExecution $stmtExec, string $name, ?CursorProperties $properties = null)
    {
        $this->stmtExec = $stmtExec;
        $this->name = $name;
        $this->properties = $properties;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Returns characteristics of the cursor.
     *
     * Note that finding out for the first time might invoke a database query. (For next time, it is cached.)
     */
    public function getProperties(): CursorProperties
    {
        if ($this->properties === null) {
            $t = $this->stmtExec->querySingleTuple(
                'SELECT is_holdable, is_scrollable, is_binary
                 FROM pg_catalog.pg_cursors
                 WHERE name = %s',
                $this->name
            );
            if ($t === null) {
                $this->closed = true;
                throw new ClosedCursorException($this->name);
            }
            $this->properties = new CursorProperties($t->is_holdable, $t->is_scrollable, $t->is_binary);
        }
        return $this->properties;
    }

    public function fetch(int $moveBy = 1): ?ITuple
    {
        $dirSql = $this->getRelativeDirectionSql($moveBy);
        return $this->fetchSingleImpl($dirSql);
    }

    public function fetchAt(int $position): ?ITuple
    {
        return $this->fetchSingleImpl("ABSOLUTE $position");
    }

    private function fetchSingleImpl(string $directionSql): ?ITuple
    {
        $this->assertOpen();
        return $this->stmtExec->querySingleTuple('FETCH %sql %ident', $directionSql, $this->name);
    }

    public function fetchMulti(int $count): IRelation
    {
        $this->assertOpen();
        $dirSql = $this->getCountingDirectionSql($count);
        return $this->stmtExec->query('FETCH %sql %ident', $dirSql, $this->name);
    }

    public function moveBy(int $offset): int
    {
        $dirSql = $this->getRelativeDirectionSql($offset);
        return $this->moveImpl($dirSql);
    }

    public function moveTo(int $position): int
    {
        return $this->moveImpl("ABSOLUTE $position");
    }

    public function moveAndCount(int $offset): int
    {
        $dirSql = $this->getCountingDirectionSql($offset);
        return $this->moveImpl($dirSql);
    }

    private function moveImpl(string $directionSql): int
    {
        $this->assertOpen();

        $res = $this->stmtExec->command('MOVE %sql %ident', $directionSql, $this->name);

        if (preg_match('~^MOVE (\d+)$~', $res->getCommandTag(), $m)) {
            return (int)$m[1];
        } else {
            throw new \RuntimeException('Unexpected command tag: ' . $res->getCommandTag());
        }
    }

    private function getRelativeDirectionSql(int $moveBy): string
    {
        if ($moveBy == 1) {
            return 'NEXT';
        } elseif ($moveBy == -1) {
            return 'PRIOR';
        } else {
            return "RELATIVE $moveBy";
        }
    }

    private function getCountingDirectionSql(int $count): string
    {
        if ($count == self::ALL_REMAINING) {
            return 'FORWARD ALL';
        } elseif ($count == self::ALL_FOREGOING) {
            return 'BACKWARD ALL';
        } elseif ($count >= 0) {
            return "FORWARD $count";
        } else {
            return 'BACKWARD ' . -$count;
        }
    }
    
    public function close(): void
    {
        $this->assertOpen();
        $this->stmtExec->command('CLOSE %ident', $this->name);
        $this->closed = true; // correctness: CLOSE is not rolled back upon rolling back a savepoint or transaction
    }

    /**
     * Tells whether the cursor is already closed.
     *
     * Note that until the cursor is known to be closed, calling this method will invoke a database query.
     */
    public function isClosed(): bool
    {
        if ($this->closed) {
            return true;
        }

        // Use the situation we must query the database, and find out the cursor properties if unknown yet.
        $t = $this->stmtExec->querySingleTuple(
            'SELECT is_holdable, is_scrollable, is_binary
             FROM pg_catalog.pg_cursors
             WHERE name = %s',
            $this->name
        );
        if ($t === null) {
            $this->closed = true;
            return true;
        } else {
            if ($this->properties === null) {
                $this->properties = new CursorProperties($t->is_holdable, $t->is_scrollable, $t->is_binary);
            }
            return false;
        }
    }

    /**
     * Retrieves an iterator to traverse rows of this cursor.
     *
     * {@inheritDoc}
     *
     * In the buffered mode, this implementation fetches the first batch of `$bufferSize` rows immediately, and while
     * iterating through the rows, it fetches another batch of rows in the background.
     */
    public function getIterator(int $bufferSize = 0): \Traversable
    {
        if ($bufferSize <= 0) {
            for ($i = 1; ; $i++) {
                $tuple = $this->fetchAt($i);
                if ($tuple === null) {
                    return;
                }
                yield $i => $tuple;
            }
        } else {
            $fetchSql = SqlRelationDefinition::fromPattern(
                'FETCH %sql %ident', $this->getCountingDirectionSql($bufferSize), $this->name
            );
            $this->moveTo(0);
            $i = 1;
            $buffer = $this->fetchMulti($bufferSize);
            while (true) {
                // start querying for the next batch in the background, if not at the end
                if (count($buffer) == $bufferSize) {
                    $nextBatchGen = $this->stmtExec->queryAsync($fetchSql);
                } else {
                    $nextBatchGen = null;
                }
                // read all the buffered tuples
                foreach ($buffer as $t) {
                    yield $i++ => $t;
                }
                // all read, check if we have fetched more data in the meantime, and put them in the buffer
                if ($nextBatchGen === null) {
                    return;
                }
                $buffer = $nextBatchGen->getResult();
            }
        }
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new ClosedCursorException($this->name);
        }
    }
}

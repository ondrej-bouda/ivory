<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Query\IRelationDefinition;
use Ivory\Relation\Cursor;
use Ivory\Relation\CursorProperties;
use Ivory\Relation\ICursor;

class CursorControl implements ICursorControl
{
    private $stmtExec;

    public function __construct(IStatementExecution $stmtExec)
    {
        $this->stmtExec = $stmtExec;
    }

    public function declareCursor(string $name, IRelationDefinition $relationDefinition, int $options = 0): ICursor
    {
        $isBinary = (bool)($options & ICursor::BINARY);
        $isHoldable = (bool)($options & ICursor::HOLDABLE);
        $isScrollable = null;

        $declarePattern = 'DECLARE %ident';
        if ($isBinary) {
            $declarePattern .= ' BINARY';
        }

        if ($options & ICursor::NON_SCROLLABLE) {
            if ($options & ICursor::SCROLLABLE) {
                throw new \LogicException("Cursor `$name` specified to be both scrollable and non-scrollable");
            }
            $isScrollable = false;
            $declarePattern .= ' NO SCROLL';
        } elseif ($options & ICursor::SCROLLABLE) {
            $isScrollable = true;
            $declarePattern .= ' SCROLL';
        }

        $declarePattern .= ' CURSOR';
        if ($isHoldable) {
            $declarePattern .= ' WITH HOLD';
        }

        $declarePattern .= ' FOR %rel';

        $this->stmtExec->command($declarePattern, $name, $relationDefinition);
        if ($isScrollable !== null) {
            $props = new CursorProperties($isHoldable, $isScrollable, $isBinary);
        } else {
            $props = null;
        }
        return new Cursor($this->stmtExec, $name, $props);
    }

    public function getAllCursors(): array
    {
        $rel = $this->stmtExec->query(
            "SELECT name, is_holdable, is_scrollable, is_binary
             FROM pg_catalog.pg_cursors
             WHERE name != ''
             ORDER BY creation_time"
        );
        $result = [];
        foreach ($rel as $tuple) {
            $name = $tuple->name;
            $props = new CursorProperties($tuple->is_holdable, $tuple->is_scrollable, $tuple->is_binary);
            $result[$name] = new Cursor($this->stmtExec, $name, $props);
        }
        return $result;
    }

    public function closeAllCursors(): void
    {
        $this->stmtExec->command('CLOSE ALL');
    }
}

<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\IvoryTestCase;
use Ivory\Value\Composite;

/**
 * This test shows composites in Ivory.
 *
 * @see https://www.postgresql.org/docs/11/rowtypes.html
 */
class CompositeTest extends IvoryTestCase
{
    public function testStoredComposites()
    {
        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $conn->command('CREATE TYPE parse_error AS (file TEXT, line INT, message TEXT)');
            $val = $conn->querySingleValue("SELECT ('foo.json', 3, 'Unexpected )')::parse_error");
            assert($val instanceof Composite);
            self::assertSame('foo.json', $val->file);
            self::assertSame(3, $val->line);
            self::assertSame('Unexpected )', $val->message);

            $err = Composite::fromMap(['file' => 'bar.c', 'line' => 2]);
            $line = $conn->querySingleValue('SELECT (%parse_error).line', $err);
            self::assertSame(2, $line);
        } finally {
            $tx->rollback();
        }
    }

    public function testRecordComposites()
    {
        $conn = $this->getIvoryConnection();

        $record = $conn->querySingleValue("SELECT ROW('a', -3, 9.81)");
        self::assertSame(['a', '-3', '9.81'], $record);

        $v = $conn->querySingleValue(
            "SELECT %record < (4, 'foo', 3.5)",
            [5e-34, 'bar', 8.9]
        );
        self::assertTrue($v);
    }
}

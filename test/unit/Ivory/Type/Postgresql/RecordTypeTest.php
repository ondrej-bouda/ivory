<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\IvoryTestCase;

class RecordTypeTest extends IvoryTestCase
{
    /** @var RecordType */
    private $recordType;

    protected function setUp(): void
    {
        parent::setUp();

        $conn = $this->getIvoryConnection();

        $this->recordType = new RecordType('pg_catalog', 'record');
        $this->recordType->attachToConnection($conn);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->recordType->detachFromConnection();
    }

    public function testParseSimple()
    {
        self::assertSame([], $this->recordType->parseValue('()'));
        self::assertSame(['1'], $this->recordType->parseValue('(1)'));
        self::assertSame(['ab'], $this->recordType->parseValue('(ab)'));
        self::assertSame(['1', 'ab'], $this->recordType->parseValue('(1,ab)'));
        self::assertSame([null, '1.2'], $this->recordType->parseValue('(,1.2)'));
    }

    public function testParseSpecialStrings()
    {
        self::assertSame([' '], $this->recordType->parseValue('( )'));

        self::assertSame(
            [null, 'NULL', '', '()', ',', '1\\2', 'p q', '"r"', "'", '"', ' , a \\ " ( ) \' '],
            $this->recordType->parseValue(<<<'STR'
(,NULL,"","()",",","1\\2","p q","\"r\"",',"""", \, \a \\ \" \( \) ' )
STR
            )
        );
    }

    public function testSerializeSimple()
    {
        self::assertSame('NULL', $this->recordType->serializeValue(null, false));
        self::assertSame('NULL::pg_catalog.record', $this->recordType->serializeValue(null));
        self::assertSame('ROW()', $this->recordType->serializeValue([]));
        self::assertSame('ROW(NULL)', $this->recordType->serializeValue([null], false));
        self::assertSame('ROW(NULL::pg_catalog.text)', $this->recordType->serializeValue([null]));
        self::assertSame("ROW(1)", $this->recordType->serializeValue([1], false));
        self::assertSame("ROW('ab')", $this->recordType->serializeValue(['ab'], false));
        self::assertSame("(1,'ab')", $this->recordType->serializeValue([1, 'ab'], false));
        self::assertSame("(1,2)", $this->recordType->serializeValue([1, 2], false));
        self::assertSame("(NULL,1.2)", $this->recordType->serializeValue([null, 1.2], false));
    }

    public function testSerializeSpecialStrings()
    {
        self::assertSame(
            <<<'STR'
(NULL,'NULL','','()',',','1\2','p q','"r"','''','"',' , \ " ''')
STR
            ,
            $this->recordType->serializeValue(
                [null, 'NULL', '', '()', ',', '1\\2', 'p q', '"r"', "'", '"', ' , \\ " \''],
                false
            )
        );
    }
}

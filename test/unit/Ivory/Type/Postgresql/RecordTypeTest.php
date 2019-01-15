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
        $this->recordType->detachFromConnection();
    }

    public function testParseSimple()
    {
        $this->assertSame([], $this->recordType->parseValue('()'));
        $this->assertSame(['1'], $this->recordType->parseValue('(1)'));
        $this->assertSame(['ab'], $this->recordType->parseValue('(ab)'));
        $this->assertSame(['1', 'ab'], $this->recordType->parseValue('(1,ab)'));
        $this->assertSame([null, '1.2'], $this->recordType->parseValue('(,1.2)'));
    }

    public function testParseSpecialStrings()
    {
        $this->assertSame([' '], $this->recordType->parseValue('( )'));

        $this->assertSame(
            [null, 'NULL', '', '()', ',', '1\\2', 'p q', '"r"', "'", '"', ' , a \\ " ( ) \' '],
            $this->recordType->parseValue(<<<'STR'
(,NULL,"","()",",","1\\2","p q","\"r\"",',"""", \, \a \\ \" \( \) ' )
STR
            )
        );
    }

    public function testSerializeSimple()
    {
        $this->assertSame('NULL', $this->recordType->serializeValue(null, false));
        $this->assertSame('NULL::pg_catalog.record', $this->recordType->serializeValue(null));
        $this->assertSame('ROW()', $this->recordType->serializeValue([]));
        $this->assertSame('ROW(NULL)', $this->recordType->serializeValue([null], false));
        $this->assertSame('ROW(NULL::pg_catalog.text)', $this->recordType->serializeValue([null]));
        $this->assertSame("ROW(1)", $this->recordType->serializeValue([1], false));
        $this->assertSame("ROW('ab')", $this->recordType->serializeValue(['ab'], false));
        $this->assertSame("(1,'ab')", $this->recordType->serializeValue([1, 'ab'], false));
        $this->assertSame("(1,2)", $this->recordType->serializeValue([1, 2], false));
        $this->assertSame("(NULL,1.2)", $this->recordType->serializeValue([null, 1.2], false));
    }

    public function testSerializeSpecialStrings()
    {
        $this->assertSame(
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

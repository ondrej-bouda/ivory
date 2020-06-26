<?php
declare(strict_types=1);
namespace Ivory\Lang\SqlPattern;

use Ivory\Exception\NoDataException;
use Ivory\IvoryTestCase;

class SqlPatternTest extends IvoryTestCase
{
    /** @var ISqlPatternParser */
    private $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SqlPatternParser();
    }

    public function testGenerateSql()
    {
        $pattern = $this->parser->parse(
            'SELECT id FROM %n:tbl UNION SELECT object_id FROM log WHERE table = %:tbl'
        );
        $gen = $pattern->generateSql();
        while ($gen->valid()) {
            $plcHdr = $gen->current();
            assert($plcHdr instanceof SqlPatternPlaceholder);
            if ($plcHdr->getNameOrPosition() == 'tbl' && $plcHdr->getTypeName() == 'n') {
                $gen->send('person');
            } elseif ($plcHdr->getNameOrPosition() == 'tbl' && $plcHdr->getTypeName() === null) {
                $gen->send("'person'");
            } else {
                static::fail('Unexpected parameter to give value for: ' . $plcHdr->getNameOrPosition());
            }
        }

        self::assertEquals(
            "SELECT id FROM person UNION SELECT object_id FROM log WHERE table = 'person'",
            $gen->getReturn()
        );
    }

    public function testGenerateAutoTypedSql()
    {
        $pattern = $this->parser->parse(
            'SELECT * FROM %:tbl WHERE name = % AND ord %% 2 = 0 AND %:cond'
        );
        $gen = $pattern->generateSql();
        while ($gen->valid()) {
            $plcHdr = $gen->current();
            assert($plcHdr instanceof SqlPatternPlaceholder);
            if ($plcHdr->getNameOrPosition() == 'tbl') {
                static::assertNull($plcHdr->getTypeName());
                $gen->send('t');
            } elseif ($plcHdr->getNameOrPosition() == 'cond') {
                static::assertNull($plcHdr->getTypeName());
                $gen->send('TRUE');
            } elseif ($plcHdr->getNameOrPosition() == 0) {
                static::assertNull($plcHdr->getTypeName());
                $gen->send("'foo'");
            } else {
                static::fail('Unexpected parameter to give value for: ' . $plcHdr->getNameOrPosition());
            }
        }
    }

    public function testGenerateSqlNotProvidingValue()
    {
        $pattern = $this->parser->parse(
            'SELECT * FROM person WHERE firstname = %s:firstname AND lastname = %s:lastname'
        );
        $gen = $pattern->generateSql();
        $gen->send('John'); // provide the value for the first placeholder
        $this->expectException(NoDataException::class);
        $gen->next(); // but not for the next one
    }
}

<?php
namespace Ivory\Lang\SqlPattern;

use Ivory\Exception\NoDataException;
use Ivory\IvoryTestCase;

class SqlPatternTest extends IvoryTestCase
{
    /** @var SqlPatternParser */
    private $parser;
    /** @var SqlPattern */
    private $pattern;

    protected function setUp()
    {
        $this->parser = new SqlPatternParser();
        $this->pattern = $this->parser->parse(
            'SELECT * FROM %:tbl WHERE name = % AND ord %% 2 = 0 AND %s:cond'
        );
    }


    public function testGenerateSql()
    {
        $pattern = $this->parser->parse(
            'SELECT id FROM %n:tbl UNION SELECT object_id FROM log WHERE table = %s:tbl'
        );
        $gen = $pattern->generateSql();
        while ($gen->valid()) {
            /** @var SqlPatternPlaceholder $plcHdr */
            $plcHdr = $gen->current();
            if ($plcHdr->getNameOrPosition() == 'tbl' && $plcHdr->getTypeName() == 'n') {
                $gen->send('person');
            } elseif ($plcHdr->getNameOrPosition() == 'tbl' && $plcHdr->getTypeName() == 's') {
                $gen->send("'person'");
            } else {
                $this->fail('Unexpected parameter to give value for.');
            }
        }

        $this->assertEquals(
            "SELECT id FROM person UNION SELECT object_id FROM log WHERE table = 'person'",
            $gen->getReturn()
        );
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

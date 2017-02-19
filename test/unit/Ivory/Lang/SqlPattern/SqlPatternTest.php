<?php
namespace Ivory\Lang\SqlPattern;

use Ivory\Exception\NoDataException;
use Ivory\IvoryTestCase;

class SqlPatternTest extends IvoryTestCase
{
    use \Ivory\PHPUnitExt;

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


    public function testFillSqlCorrect()
    {
        $this->assertEquals(
            "SELECT * FROM person WHERE name = 'John' AND ord % 2 = 0 AND is_active",
            $this->pattern->fillSql([
                'cond' => 'is_active',
                0 => "'John'",
                'tbl' => 'person',
            ])
        );
    }

    public function testFillSqlInsufficientArguments()
    {
        $this->activateAssertionExceptions();
        try {
            $this->assertException(\InvalidArgumentException::class, null, function () {
                $this->pattern->fillSql(['cond' => 'is_active', 0 => "'John'"]);
            });
        } finally {
            $this->restoreAssertionsConfig();
        }
    }

    public function testFillSqlExtraArguments()
    {
        $this->activateAssertionExceptions();
        try {
            $this->assertException(\InvalidArgumentException::class, null, function () {
                $this->pattern->fillSql(['cond' => 'is_active', 0 => "'John'", 'tbl' => 'person', 'foo' => 'bar']);
            });
        } finally {
            $this->restoreAssertionsConfig();
        }
    }

    public function testFillSqlWrongArguments()
    {
        $this->activateAssertionExceptions();
        try {
            $this->assertException(\InvalidArgumentException::class, null, function () {
                $this->pattern->fillSql(['cond' => 'is_active', 0 => "'John'", 'foo' => 'bar']);
            });
        } finally {
            $this->restoreAssertionsConfig();
        }
    }

    public function testFillSqlInvalidArguments()
    {
        $this->expectException(\PHPUnit\Framework\Error\Notice::class);
        $this->pattern->fillSql(['cond' => [], 0 => "'John'", 'tbl' => 'person']);
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

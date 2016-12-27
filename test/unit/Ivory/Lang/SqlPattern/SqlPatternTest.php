<?php
namespace Ivory\Lang\SqlPattern;

use Ivory\Exception\NoDataException;

class SqlPatternTest extends \PHPUnit_Framework_TestCase
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
			$this->pattern->fillSql(['cond' => 'is_active', 0 => "'John'"]);
			$this->fail('InvalidArgumentException expected due to insufficient arguments');
		}
		catch (\InvalidArgumentException $e) {
		}
		finally {
			$this->restoreAssertionsConfig();
		}
	}

	public function testFillSqlExtraArguments()
	{
		$this->activateAssertionExceptions();
		try {
			$this->pattern->fillSql(['cond' => 'is_active', 0 => "'John'", 'tbl' => 'person', 'foo' => 'bar']);
			$this->fail('InvalidArgumentException expected due to extra arguments');
		}
		catch (\InvalidArgumentException $e) {
		}
		finally {
			$this->restoreAssertionsConfig();
		}
	}

	public function testFillSqlWrongArguments()
	{
		$this->activateAssertionExceptions();
		try {
			$this->pattern->fillSql(['cond' => 'is_active', 0 => "'John'", 'foo' => 'bar']);
			$this->fail('InvalidArgumentException expected due to wrong arguments');
		}
		catch (\InvalidArgumentException $e) {
		}
		finally {
			$this->restoreAssertionsConfig();
		}
	}

    /**
     * @expectedException \PHPUnit_Framework_Error_Notice
     */
	public function testFillSqlInvalidArguments()
	{
        $this->pattern->fillSql(['cond' => [], 0 => "'John'", 'tbl' => 'person']);
	}

    public function testGenerateSql()
    {
        $pattern = $this->parser->parse(
            'SELECT id FROM %n:tbl UNION SELECT object_id FROM log WHERE table = %s:tbl'
        );
        $gen = $pattern->generateSql();
        foreach ($gen as $plcHdr => &$value) {
            /** @var SqlPatternPlaceholder $plcHdr */
            if ($plcHdr->getNameOrPosition() == 'tbl' && $plcHdr->getTypeName() == 'n') {
                $value = 'person';
            }
            elseif ($plcHdr->getNameOrPosition() == 'tbl' && $plcHdr->getTypeName() == 's') {
                $value = "'person'";
            }
            else {
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
        $this->activateAssertionExceptions();

        $pattern = $this->parser->parse(
            'SELECT id FROM %n:tbl UNION SELECT object_id FROM log WHERE table = %s:tbl'
        );
        $gen = $pattern->generateSql();
        $i = 0;
        try {
            foreach ($gen as $plcHdr => &$value) {
                if ($i > 0) {
                    break;
                }
                $i++;
            }
            $this->fail("Did not stop due to the value not being filled.");
        }
        catch (NoDataException $e) {
        }
        finally {
            $this->restoreAssertionsConfig();
        }
    }
}

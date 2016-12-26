<?php
namespace Ivory\Lang\SqlPattern;

class SqlPatternTest extends \PHPUnit_Framework_TestCase
{
	use \Ivory\PHPUnitExt;

	/** @var SqlPattern */
	private $pattern;

	protected function setUp()
	{
		$parser = new SqlPatternParser();
		$this->pattern = $parser->parse(
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
}

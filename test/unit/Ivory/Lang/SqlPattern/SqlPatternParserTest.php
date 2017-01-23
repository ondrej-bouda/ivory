<?php
namespace Ivory\Lang\SqlPattern;

class SqlPatternParserTest extends \PHPUnit_Framework_TestCase
{
	/** @var SqlPatternParser */
	private $parser;

	protected function setUp()
	{
		$this->parser = new SqlPatternParser();
	}

	public function testPositionalPlaceholder()
	{
		$pattern = $this->parser->parse('SELECT * FROM person WHERE id = % AND is_active');
		$this->assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getRawSql());
		$this->assertEquals(
			[new SqlPatternPlaceholder(32, 0, null)],
			$pattern->getPositionalPlaceholders()
		);
		$this->assertEmpty($pattern->getNamedPlaceholderMap());
	}

	public function testPositionalTypedPlaceholder()
	{
		$pattern = $this->parser->parse('SELECT * FROM person WHERE id = %ivory.d AND is_active');
		$this->assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getRawSql());
		$this->assertEquals(
			[new SqlPatternPlaceholder(32, 0, 'ivory.d')],
			$pattern->getPositionalPlaceholders()
		);
		$this->assertEmpty($pattern->getNamedPlaceholderMap());
	}

	public function testNamedPlaceholder()
	{
		$pattern = $this->parser->parse('SELECT * FROM person WHERE id = %:id AND is_active');
		$this->assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getRawSql());
		$this->assertEmpty($pattern->getPositionalPlaceholders());
		$this->assertEquals(
			['id' => [new SqlPatternPlaceholder(32, 'id', null)]],
			$pattern->getNamedPlaceholderMap()
		);
	}

	public function testNamedTypedPlaceholder()
	{
		$pattern = $this->parser->parse('SELECT * FROM person WHERE id = %d:id AND is_active');
		$this->assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getRawSql());
		$this->assertEmpty($pattern->getPositionalPlaceholders());
		$this->assertEquals(
			['id' => [new SqlPatternPlaceholder(32, 'id', 'd')]],
			$pattern->getNamedPlaceholderMap()
		);
	}

    public function testSquareBrackets()
    {
        $pattern = $this->parser->parse('SELECT %bigint[][][][2]');
        $this->assertEquals('SELECT [2]', $pattern->getRawSql());
        $this->assertEquals(
            [new SqlPatternPlaceholder(7, 0, 'bigint[]')],
            $pattern->getPositionalPlaceholders()
        );
        $this->assertEmpty($pattern->getNamedPlaceholderMap());
    }

	public function testMultiplePlaceholders()
	{
		$pattern = $this->parser->parse(
			'SELECT * FROM %:tbl WHERE name = % AND %:cond AND ord %% 2 = %d AND %(name) AND %s:cond'
		);

		$this->assertEquals(
			'SELECT * FROM  WHERE name =  AND  AND ord % 2 =  AND (name) AND ',
			$pattern->getRawSql()
		);

		$this->assertEquals(
			[
				new SqlPatternPlaceholder(28, 0, null),
				new SqlPatternPlaceholder(48, 1, 'd'),
				new SqlPatternPlaceholder(53, 2, null),
			],
			$pattern->getPositionalPlaceholders()
		);

		$this->assertEquals(
			[
				'tbl' => [
					new SqlPatternPlaceholder(14, 'tbl', null),
				],
				'cond' => [
					new SqlPatternPlaceholder(33, 'cond', null),
					new SqlPatternPlaceholder(64, 'cond', 's'),
				],
			],
			$pattern->getNamedPlaceholderMap()
		);
	}

	public function testPercentEscapes()
	{
		$pattern = $this->parser->parse('SELECT * FROM person WHERE id %% 2 = 0');
		$this->assertEquals('SELECT * FROM person WHERE id % 2 = 0', $pattern->getRawSql());
		$this->assertEmpty($pattern->getPositionalPlaceholders());
		$this->assertEmpty($pattern->getNamedPlaceholderMap());
	}
}
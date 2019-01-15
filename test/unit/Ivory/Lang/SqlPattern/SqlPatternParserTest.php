<?php
declare(strict_types=1);
namespace Ivory\Lang\SqlPattern;

class SqlPatternParserTest extends \PHPUnit\Framework\TestCase
{
    /** @var ISqlPatternParser */
    private $parser;

    protected function setUp()
    {
        $this->parser = new SqlPatternParser();
    }

    public function testPositionalPlaceholder()
    {
        $pattern = $this->parser->parse('SELECT * FROM person WHERE id = % AND is_active');
        $this->assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getSqlTorso());
        $this->assertEquals(
            [new SqlPatternPlaceholder(32, 0, null)],
            $pattern->getPositionalPlaceholders()
        );
        $this->assertEmpty($pattern->getNamedPlaceholderMap());
    }

    public function testPositionalTypedPlaceholder()
    {
        $pattern = $this->parser->parse('SELECT * FROM person WHERE id = %ivory.d AND is_active');
        $this->assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getSqlTorso());
        $this->assertEquals(
            [new SqlPatternPlaceholder(32, 0, 'd', false, 'ivory', false)],
            $pattern->getPositionalPlaceholders()
        );
        $this->assertEmpty($pattern->getNamedPlaceholderMap());
    }

    public function testNamedPlaceholder()
    {
        $pattern = $this->parser->parse('SELECT * FROM person WHERE id = %:id AND is_active');
        $this->assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getSqlTorso());
        $this->assertEmpty($pattern->getPositionalPlaceholders());
        $this->assertEquals(
            ['id' => [new SqlPatternPlaceholder(32, 'id', null)]],
            $pattern->getNamedPlaceholderMap()
        );
    }

    public function testNamedTypedPlaceholder()
    {
        $pattern = $this->parser->parse('SELECT * FROM person WHERE id = %i:id AND is_active');
        $this->assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getSqlTorso());
        $this->assertEmpty($pattern->getPositionalPlaceholders());
        $this->assertEquals(
            ['id' => [new SqlPatternPlaceholder(32, 'id', 'i')]],
            $pattern->getNamedPlaceholderMap()
        );
    }

    public function testSquareBrackets()
    {
        $pattern = $this->parser->parse('SELECT %bigint[][][][2]');
        $this->assertEquals('SELECT [2]', $pattern->getSqlTorso());
        $this->assertEquals(
            [new SqlPatternPlaceholder(7, 0, 'bigint[]')],
            $pattern->getPositionalPlaceholders()
        );
        $this->assertEmpty($pattern->getNamedPlaceholderMap());
    }

    public function testCurlyBracesInTypeSpecification()
    {
        $pattern = $this->parser->parse('SELECT %{double precision}, %{"quotes included"}:named');
        $this->assertEquals('SELECT , ', $pattern->getSqlTorso());
        $this->assertEquals(
            [
                new SqlPatternPlaceholder(7, 0, 'double precision'),
            ],
            $pattern->getPositionalPlaceholders()
        );
        $this->assertEquals(
            [
                'named' => [
                    new SqlPatternPlaceholder(9, 'named', '"quotes included"'),
                ],
            ],
            $pattern->getNamedPlaceholderMap()
        );
    }

    public function testQuotedTypeSpecification()
    {
        $pattern = $this->parser->parse('SELECT %"quoted type", %"schema with """."t", %"z":named');
        $this->assertEquals('SELECT , , ', $pattern->getSqlTorso());
        $this->assertEquals(
            [
                new SqlPatternPlaceholder(7, 0, 'quoted type', true),
                new SqlPatternPlaceholder(9, 1, 't', true, 'schema with "', true),
            ],
            $pattern->getPositionalPlaceholders()
        );
        $this->assertEquals(
            [
                'named' => [
                    new SqlPatternPlaceholder(11, 'named', 'z', true),
                ],
            ],
            $pattern->getNamedPlaceholderMap()
        );
    }

    public function testMultiplePlaceholders()
    {
        $pattern = $this->parser->parse(
            'SELECT * FROM %:tbl WHERE name = % AND %:cond AND ord %% 2 = %i AND %(name) AND %s:cond'
        );

        $this->assertEquals(
            'SELECT * FROM  WHERE name =  AND  AND ord % 2 =  AND (name) AND ',
            $pattern->getSqlTorso()
        );

        $this->assertEquals(
            [
                new SqlPatternPlaceholder(28, 0, null),
                new SqlPatternPlaceholder(48, 1, 'i'),
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
        $this->assertEquals('SELECT * FROM person WHERE id % 2 = 0', $pattern->getSqlTorso());
        $this->assertEmpty($pattern->getPositionalPlaceholders());
        $this->assertEmpty($pattern->getNamedPlaceholderMap());
    }

    public function testLooseTypeModePlaceholders()
    {
        $pattern = $this->parser->parse('SELECT %?, %t?, %?:name, %t?:name');
        $this->assertEquals('SELECT , , , ', $pattern->getSqlTorso());
        $this->assertEquals(
            [
                new SqlPatternPlaceholder(7, 0, null, false, null, false, true),
                new SqlPatternPlaceholder(9, 1, 't', false, null, false, true),
            ],
            $pattern->getPositionalPlaceholders()
        );
        $this->assertEquals(
            [
                'name' => [
                    new SqlPatternPlaceholder(11, 'name', null, false, null, false, true),
                    new SqlPatternPlaceholder(13, 'name', 't', false, null, false, true),
                ],
            ],
            $pattern->getNamedPlaceholderMap()
        );
    }
}

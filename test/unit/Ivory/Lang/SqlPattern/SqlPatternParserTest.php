<?php
declare(strict_types=1);
namespace Ivory\Lang\SqlPattern;

use PHPUnit\Framework\TestCase;

class SqlPatternParserTest extends TestCase
{
    /** @var ISqlPatternParser */
    private $parser;

    protected function setUp(): void
    {
        parent::setUp();
        $this->parser = new SqlPatternParser();
    }

    public function testPositionalPlaceholder(): void
    {
        $pattern = $this->parser->parse('SELECT * FROM person WHERE id = % AND is_active');
        self::assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getSqlTorso());
        self::assertEquals(
            [new SqlPatternPlaceholder(32, 0, null)],
            $pattern->getPositionalPlaceholders()
        );
        self::assertEmpty($pattern->getNamedPlaceholderMap());
    }

    public function testPositionalTypedPlaceholder(): void
    {
        $pattern = $this->parser->parse('SELECT * FROM person WHERE id = %ivory.d AND is_active');
        self::assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getSqlTorso());
        self::assertEquals(
            [new SqlPatternPlaceholder(32, 0, 'd', false, 'ivory', false)],
            $pattern->getPositionalPlaceholders()
        );
        self::assertEmpty($pattern->getNamedPlaceholderMap());
    }

    public function testNamedPlaceholder(): void
    {
        $pattern = $this->parser->parse('SELECT * FROM person WHERE id = %:id AND is_active');
        self::assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getSqlTorso());
        self::assertEmpty($pattern->getPositionalPlaceholders());
        self::assertEquals(
            ['id' => [new SqlPatternPlaceholder(32, 'id', null)]],
            $pattern->getNamedPlaceholderMap()
        );
    }

    public function testNamedTypedPlaceholder(): void
    {
        $pattern = $this->parser->parse('SELECT * FROM person WHERE id = %i:id AND is_active');
        self::assertEquals('SELECT * FROM person WHERE id =  AND is_active', $pattern->getSqlTorso());
        self::assertEmpty($pattern->getPositionalPlaceholders());
        self::assertEquals(
            ['id' => [new SqlPatternPlaceholder(32, 'id', 'i')]],
            $pattern->getNamedPlaceholderMap()
        );
    }

    public function testSquareBrackets(): void
    {
        $pattern = $this->parser->parse('SELECT %bigint[][][][2]');
        self::assertEquals('SELECT [2]', $pattern->getSqlTorso());
        self::assertEquals(
            [new SqlPatternPlaceholder(7, 0, 'bigint[]')],
            $pattern->getPositionalPlaceholders()
        );
        self::assertEmpty($pattern->getNamedPlaceholderMap());
    }

    public function testCurlyBracesInTypeSpecification(): void
    {
        $pattern = $this->parser->parse('SELECT %{double precision}, %{"quotes included"}:named');
        self::assertEquals('SELECT , ', $pattern->getSqlTorso());
        self::assertEquals(
            [
                new SqlPatternPlaceholder(7, 0, 'double precision'),
            ],
            $pattern->getPositionalPlaceholders()
        );
        self::assertEquals(
            [
                'named' => [
                    new SqlPatternPlaceholder(9, 'named', '"quotes included"'),
                ],
            ],
            $pattern->getNamedPlaceholderMap()
        );
    }

    public function testQuotedTypeSpecification(): void
    {
        $pattern = $this->parser->parse('SELECT %"quoted type", %"schema with """."t", %"z":named');
        self::assertEquals('SELECT , , ', $pattern->getSqlTorso());
        self::assertEquals(
            [
                new SqlPatternPlaceholder(7, 0, 'quoted type', true),
                new SqlPatternPlaceholder(9, 1, 't', true, 'schema with "', true),
            ],
            $pattern->getPositionalPlaceholders()
        );
        self::assertEquals(
            [
                'named' => [
                    new SqlPatternPlaceholder(11, 'named', 'z', true),
                ],
            ],
            $pattern->getNamedPlaceholderMap()
        );
    }

    public function testMultiplePlaceholders(): void
    {
        $pattern = $this->parser->parse(
            'SELECT * FROM %:tbl WHERE name = % AND %:cond AND ord %% 2 = %i AND %(name) AND %s:cond'
        );

        self::assertEquals(
            'SELECT * FROM  WHERE name =  AND  AND ord % 2 =  AND (name) AND ',
            $pattern->getSqlTorso()
        );

        self::assertEquals(
            [
                new SqlPatternPlaceholder(28, 0, null),
                new SqlPatternPlaceholder(48, 1, 'i'),
                new SqlPatternPlaceholder(53, 2, null),
            ],
            $pattern->getPositionalPlaceholders()
        );

        self::assertEquals(
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

    public function testPercentEscapes(): void
    {
        $pattern = $this->parser->parse('SELECT * FROM person WHERE id %% 2 = 0');
        self::assertEquals('SELECT * FROM person WHERE id % 2 = 0', $pattern->getSqlTorso());
        self::assertEmpty($pattern->getPositionalPlaceholders());
        self::assertEmpty($pattern->getNamedPlaceholderMap());
    }

    public function testLooseTypeModePlaceholders(): void
    {
        $pattern = $this->parser->parse('SELECT %?, %t?, %?:name, %t?:name');
        self::assertEquals('SELECT , , , ', $pattern->getSqlTorso());
        self::assertEquals(
            [
                new SqlPatternPlaceholder(7, 0, null, false, null, false, true),
                new SqlPatternPlaceholder(9, 1, 't', false, null, false, true),
            ],
            $pattern->getPositionalPlaceholders()
        );
        self::assertEquals(
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

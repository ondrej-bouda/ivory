<?php
namespace Ivory\Connection;

use Ivory\Query\SqlRelationRecipe;
use Ivory\Type\ITypeDictionary;

class TypeControlTest extends \Ivory\IvoryTestCase
{
    /** @var ITypeDictionary */
    private $typeDict;

    protected function setUp()
    {
        $conn = $this->getIvoryConnection();
        $this->typeDict = $conn->getTypeDictionary();
    }


    public function testSqlRecipeTypeAliases()
    {
        $recip = SqlRelationRecipe::fromPattern('SELECT %s, %n', "Ivory's escaping", 3.14);
        $sql = $recip->toSql($this->typeDict);
        $this->assertSame("SELECT 'Ivory''s escaping', 3.14", $sql);
    }

    public function testSqlRecipeAliasedTypes()
    {
        $recip = SqlRelationRecipe::fromPattern('SELECT %integer', 42);
        $sql = $recip->toSql($this->typeDict);
        $this->assertSame("SELECT 42", $sql);
    }

    public function testSqlRecipeTypeQualifiedNames()
    {
        $recip = SqlRelationRecipe::fromPattern('SELECT %pg_catalog.text, %pg_catalog.numeric', "Ivory's escaping", 3.14);
        $sql = $recip->toSql($this->typeDict);
        $this->assertSame("SELECT 'Ivory''s escaping', 3.14", $sql);
    }

    public function testSqlRecipeTypeUnqualifiedNames()
    {
        $recip = SqlRelationRecipe::fromPattern('SELECT %text, %numeric', "Ivory's escaping", 3.14);
        $sql = $recip->toSql($this->typeDict);
        $this->assertSame("SELECT 'Ivory''s escaping', 3.14", $sql);
    }

    public function testSqlRecipeArrayTypes()
    {
        $recip = SqlRelationRecipe::fromPattern('SELECT %pg_catalog.text[], %integer[]', ["Ivory's escaping"], [1 => 4, 5, 2, 3]);
        $sql = $recip->toSql($this->typeDict);
        $this->assertSame("SELECT '[0:0]={\"Ivory''s escaping\"}'::pg_catalog.text[], '{4,5,2,3}'::pg_catalog.int4[]", $sql);
    }
}

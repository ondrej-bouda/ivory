<?php
namespace Ivory\Query;

use Ivory\Connection\ConfigParam;
use Ivory\Connection\IConnection;
use Ivory\Exception\UndefinedTypeException;
use Ivory\Type\ITypeDictionary;

class SqlRecipeTest extends \Ivory\IvoryTestCase
{
    /** @var IConnection */
    private $conn;
    /** @var ITypeDictionary */
    private $typeDict;

    protected function setUp()
    {
        $this->conn = $this->getIvoryConnection();
        $this->conn->startTransaction();
        $this->typeDict = $this->conn->getTypeDictionary();
    }

    protected function tearDown()
    {
        $this->conn->rollback();
    }


    public function testTypeAliases()
    {
        $recip = SqlRelationRecipe::fromPattern('SELECT %s, %n', "Ivory's escaping", 3.14);
        $sql = $recip->toSql($this->typeDict);
        $this->assertSame("SELECT 'Ivory''s escaping', 3.14", $sql);
    }

    public function testAliasedTypes()
    {
        $recip = SqlRelationRecipe::fromPattern('SELECT %integer', 42);
        $sql = $recip->toSql($this->typeDict);
        $this->assertSame("SELECT 42", $sql);
    }

    public function testTypeQualifiedNames()
    {
        $recip = SqlRelationRecipe::fromPattern('SELECT %pg_catalog.text, %pg_catalog.numeric', "Ivory's escaping", 3.14);
        $sql = $recip->toSql($this->typeDict);
        $this->assertSame("SELECT 'Ivory''s escaping', 3.14", $sql);
    }

    public function testTypeUnqualifiedNames()
    {
        $recip = SqlRelationRecipe::fromPattern('SELECT %text, %numeric', "Ivory's escaping", 3.14);
        $sql = $recip->toSql($this->typeDict);
        $this->assertSame("SELECT 'Ivory''s escaping', 3.14", $sql);
    }

    public function testArrayTypes()
    {
        $recip = SqlRelationRecipe::fromPattern('SELECT %pg_catalog.text[], %integer[]', ["Ivory's escaping"], [1 => 4, 5, 2, 3]);
        $this->assertSame(
            "SELECT '[0:0]={\"Ivory''s escaping\"}'::pg_catalog.text[], '{4,5,2,3}'::pg_catalog.int4[]",
            $recip->toSql($this->typeDict)
        );

        $recip = SqlRelationRecipe::fromPattern('SELECT %int[][][]', [1 => 42]);
        $this->assertSame("SELECT '{42}'::pg_catalog.int4[]", $recip->toSql($this->typeDict));
    }

    public function testQuotedTypeNames()
    {
        $this->conn->rawQuery('CREATE DOMAIN public."name with "" and ." AS TEXT');
        $recip = SqlRelationRecipe::fromPattern('SELECT %public."name with "" and ."', 'Ivory');
        $this->assertSame("SELECT 'Ivory'", $recip->toSql($this->typeDict));

        $this->conn->rawQuery('CREATE DOMAIN public."int" AS TEXT');
        $recip = SqlRelationRecipe::fromPattern('SELECT %public."int"', '42');
        $this->assertSame("SELECT '42'", $recip->toSql($this->typeDict));

        $recip = SqlRelationRecipe::fromPattern('SELECT %"int"', '42');
        $this->assertSame("SELECT '42'", $recip->toSql($this->typeDict));

        $recip = SqlRelationRecipe::fromPattern('SELECT %int', '42');
        $this->assertSame('SELECT 42', $recip->toSql($this->typeDict));
    }

    public function testQuotedSchemaNames()
    {
        $this->conn->rawQuery('CREATE SCHEMA "__Ivory_test"');
        $this->conn->rawQuery('CREATE DOMAIN "__Ivory_test".d AS INT');

        $recip = SqlRelationRecipe::fromPattern('SELECT %"__Ivory_test".d', 42);
        $this->assertSame('SELECT 42', $recip->toSql($this->typeDict));

        $recip = SqlRelationRecipe::fromPattern('SELECT %__ivory_test.d', 42);
        try {
            $recip->toSql($this->typeDict);
            $this->fail('%__ivory_test only matches "__ivory_test" schema, no other case variants');
        }
        catch (UndefinedTypeException $e) {
        }

        $recip = SqlRelationRecipe::fromPattern('SELECT %__Ivory_test.d', 42);
        try {
            $recip->toSql($this->typeDict);
            $this->fail('Even %__Ivory_test only matches "__ivory_test" schema - when unquoted, it gets lower-cased');
        }
        catch (UndefinedTypeException $e) {
        }


        $this->conn->rawQuery('CREATE SCHEMA "__ivory_test"');
        $this->conn->rawQuery('CREATE DOMAIN "__ivory_test".d AS TEXT');

        $recip = SqlRelationRecipe::fromPattern('SELECT %__ivory_test.d', 42);
        $this->assertSame("SELECT '42'", $recip->toSql($this->typeDict));
    }

    public function testBraceEnclosedTypeNames()
    {
        $this->conn->rawQuery('CREATE DOMAIN public."double precision" AS TEXT');

        $recip = SqlRelationRecipe::fromPattern('SELECT %"double precision"', 42);
        $this->assertSame("SELECT '42'", $recip->toSql($this->typeDict));

        $recip = SqlRelationRecipe::fromPattern('SELECT %{double precision}', 42);
        $this->assertSame('SELECT 42', $recip->toSql($this->typeDict));

        $recip = SqlRelationRecipe::fromPattern('SELECT %{"double precision"}', 42);
        try {
            $recip->toSql($this->typeDict);
            $this->fail('Type "double precision" (name including the quotes) should have not been recognized as defined.');
        }
        catch (UndefinedTypeException $e) {
        }
    }

    public function testSearchPathChange()
    {
        $this->conn->rawQuery('CREATE DOMAIN public.tp AS INT');
        $this->conn->rawQuery('CREATE SCHEMA s');
        $this->conn->rawQuery('CREATE DOMAIN s.tp AS TEXT');

        $recip = SqlRelationRecipe::fromPattern('SELECT %tp', 42);

        $this->assertSame('SELECT 42', $recip->toSql($this->typeDict));

        $this->conn->getConfig()->setForSession(ConfigParam::SEARCH_PATH, 's, public');
        $this->assertSame("SELECT '42'", $recip->toSql($this->typeDict));
    }
}

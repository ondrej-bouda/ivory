<?php
namespace Ivory\Query;

use Ivory\Connection\Config\ConfigParam;
use Ivory\Connection\IConnection;
use Ivory\Exception\UndefinedTypeException;
use Ivory\Type\ITypeDictionary;
use Ivory\Value\Box;
use Ivory\Value\Decimal;
use Ivory\Value\Time;

class SqlRecipeTest extends \Ivory\IvoryTestCase
{
    /** @var IConnection */
    private $conn;
    /** @var ITypeDictionary */
    private $typeDict;

    protected function setUp()
    {
        parent::setUp();

        $this->conn = $this->getIvoryConnection();
        $this->conn->startTransaction();
        $this->typeDict = $this->conn->getTypeDictionary();
    }

    protected function tearDown()
    {
        $this->conn->rollback();

        parent::tearDown();
    }


    public function testTypeAliases()
    {
        $recip = SqlRelationRecipe::fromPattern('SELECT %s, %num', "Ivory's escaping", 3.14);
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
        $recip = SqlRelationRecipe::fromPattern(
            'SELECT %pg_catalog.text, %pg_catalog.numeric',
            "Ivory's escaping", 3.14
        );
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
        $recip = SqlRelationRecipe::fromPattern(
            'SELECT %pg_catalog.text[], %integer[]',
            ["Ivory's escaping"],
            [1 => 4, 5, 2, 3]
        );
        $this->assertSame(
            "SELECT '[0:0]={\"Ivory''s escaping\"}'::pg_catalog.text[], '{4,5,2,3}'::pg_catalog.int4[]",
            $recip->toSql($this->typeDict)
        );

        $recip = SqlRelationRecipe::fromPattern('SELECT %int[][][]', [1 => 42]);
        $this->assertSame("SELECT '{42}'::pg_catalog.int4[]", $recip->toSql($this->typeDict));
    }

    public function testQuotedTypeNames()
    {
        $this->conn->rawCommand('CREATE DOMAIN public."name with "" and ." AS TEXT');
        $recip = SqlRelationRecipe::fromPattern('SELECT %public."name with "" and ."', 'Ivory');
        $this->assertSame("SELECT 'Ivory'", $recip->toSql($this->typeDict));

        $this->conn->rawCommand('CREATE DOMAIN public."int" AS TEXT');
        $recip = SqlRelationRecipe::fromPattern('SELECT %public."int"', '42');
        $this->assertSame("SELECT '42'", $recip->toSql($this->typeDict));

        $recip = SqlRelationRecipe::fromPattern('SELECT %"int"', '42');
        $this->assertSame("SELECT '42'", $recip->toSql($this->typeDict));

        $recip = SqlRelationRecipe::fromPattern('SELECT %int', '42');
        $this->assertSame('SELECT 42', $recip->toSql($this->typeDict));
    }

    public function testQuotedSchemaNames()
    {
        $this->conn->rawCommand('CREATE SCHEMA "__Ivory_test"');
        $this->conn->rawCommand('CREATE DOMAIN "__Ivory_test".d AS INT');

        $recip = SqlRelationRecipe::fromPattern('SELECT %"__Ivory_test".d', 42);
        $this->assertSame('SELECT 42', $recip->toSql($this->typeDict));

        $recip = SqlRelationRecipe::fromPattern('SELECT %__ivory_test.d', 42);
        try {
            $recip->toSql($this->typeDict);
            $this->fail('%__ivory_test only matches "__ivory_test" schema, no other case variants');
        } catch (UndefinedTypeException $e) {
        }

        $recip = SqlRelationRecipe::fromPattern('SELECT %__Ivory_test.d', 42);
        try {
            $recip->toSql($this->typeDict);
            $this->fail('Even %__Ivory_test only matches "__ivory_test" schema - when unquoted, it gets lower-cased');
        } catch (UndefinedTypeException $e) {
        }


        $this->conn->rawCommand('CREATE SCHEMA "__ivory_test"');
        $this->conn->rawCommand('CREATE DOMAIN "__ivory_test".d AS TEXT');

        $recip = SqlRelationRecipe::fromPattern('SELECT %__ivory_test.d', 42);
        $this->assertSame("SELECT '42'", $recip->toSql($this->typeDict));
    }

    public function testBraceEnclosedTypeNames()
    {
        $this->conn->rawCommand('CREATE DOMAIN public."double precision" AS TEXT');

        $recip = SqlRelationRecipe::fromPattern('SELECT %"double precision"', 42);
        $this->assertSame("SELECT '42'", $recip->toSql($this->typeDict));

        $recip = SqlRelationRecipe::fromPattern('SELECT %{double precision}', 42);
        $this->assertSame('SELECT 42', $recip->toSql($this->typeDict));

        $recip = SqlRelationRecipe::fromPattern('SELECT %{"double precision"}', 42);
        try {
            $recip->toSql($this->typeDict);
            $this->fail('Type "double precision" (name including the quotes) should have not been recognized as defined.');
        } catch (UndefinedTypeException $e) {
        }
    }

    public function testSearchPathChange()
    {
        $this->conn->rawCommand('CREATE DOMAIN public.tp AS INT');
        $this->conn->rawCommand('CREATE SCHEMA s');
        $this->conn->rawCommand('CREATE DOMAIN s.tp AS TEXT');

        $recip = SqlRelationRecipe::fromPattern('SELECT %tp', 42);

        $this->assertSame('SELECT 42', $recip->toSql($this->typeDict));

        $this->conn->getConfig()->setForSession(ConfigParam::SEARCH_PATH, 's, public');
        $this->assertSame("SELECT '42'", $recip->toSql($this->typeDict));
    }

    public function testFromFragments()
    {
        $recipJoinWithSpace = SqlRelationRecipe::fromFragments(
            'SELECT 1',
            'FROM tbl',
            'WHERE cond'
        );
        $this->assertSame(
            'SELECT 1 FROM tbl WHERE cond',
            $recipJoinWithSpace->getSqlPattern()->getSqlTorso(),
            'Joining using a space between fragments to prevent syntax error.'
        );


        $recipJoinWithoutSpace = SqlRelationRecipe::fromFragments(
            'SELECT 1',
            " FROM tbl\n",
            'WHERE cond',
            "\nORDER BY 1"
        );
        $this->assertSame(
            "SELECT 1 FROM tbl\nWHERE cond\nORDER BY 1",
            $recipJoinWithoutSpace->getSqlPattern()->getSqlTorso(),
            'Joining straight, with no extra space - which is unnecessary due to whitespace at the fragment bounds.'
        );


        $recipWithArgs = SqlRelationRecipe::fromFragments(
            'SELECT %int, %char', 42, 'C', 'UNION SELECT', "%integer , 'D'", 53
        );
        $this->assertSame("SELECT ,  UNION SELECT  , 'D'", $recipWithArgs->getSqlPattern()->getSqlTorso());
        $this->assertSame("SELECT 42, 'C' UNION SELECT 53 , 'D'", $recipWithArgs->toSql($this->typeDict));
    }

    /**
     * @depends testFromFragments
     */
    public function testRecipeComposition()
    {
        $recip = SqlRelationRecipe::fromFragments(
            'WITH data AS (
               %rel', SqlRelationRecipe::fromPattern('SELECT x FROM %ident', 'r'), '
             ),
             inserted AS (
               %cmd', SqlCommandRecipe::fromPattern('INSERT INTO t (a) SELECT x FROM data'), '
               RETURNING a, b
             )
             SELECT * FROM inserted %sql', 'ORDER BY 1, 2'
        );

        $this->assertSame(
            'WITH data AS (
               SELECT x FROM r
             ),
             inserted AS (
               INSERT INTO t (a) SELECT x FROM data
               RETURNING a, b
             )
             SELECT * FROM inserted ORDER BY 1, 2',
            $recip->toSql($this->typeDict)
        );
    }

    public function testNamedParametersMap()
    {
        $recip = SqlRelationRecipe::fromFragments(
            'SELECT %ident:tbl.col + %int', 1, 'FROM %ident:tbl',
            ['tbl' => 't']
        );

        $this->assertSame(
            'SELECT t.col + 1 FROM t',
            $recip->toSql($this->typeDict)
        );
    }

    public function testAutoTypesBasic()
    {
        $recip = SqlRelationRecipe::fromFragments(
            'SELECT %, %, %, %, %',
            true, 42, 3.14, 'wheee', null
        );

        $this->assertSame(
            "SELECT TRUE, 42, 3.14, 'wheee', NULL",
            $recip->toSql($this->typeDict)
        );
    }

    public function testAutoTypesObject()
    {
        $recip = SqlRelationRecipe::fromFragments(
            'SELECT %, %, %',
            Decimal::fromNumber('2.81'),
            Box::fromOppositeCorners([3, 5], [9, 14]),
            Time::fromString('13:49')
        );

        $this->assertSame(
            "SELECT 2.81, box(point(9,14),point(3,5)), '13:49:00'",
            $recip->toSql($this->typeDict)
        );
    }

    public function testAutoTypesArray()
    {
        $recip = SqlRelationRecipe::fromFragments(
            'SELECT %, %:emptyArr, %:nullStartArr, %:allNullArr, %:nestedArr, %:decArr',
            [1 => 1, 2, 3]
        );
        $recip->setParams([
            'emptyArr' => [],
            'nullStartArr' => [1 => null, null, 1.1, 2.4],
            'allNullArr' => [1 => null, null],
            'nestedArr' => [1 => [1 => null, 'b'], [1 => '{', '}']],
            'decArr' => [1 => Decimal::fromNumber(1.6), Decimal::fromNumber(8)]
        ]);

        $this->assertSame(
            "SELECT " .
            "'{1,2,3}'::pg_catalog.int8[], " .
            "'{}'::pg_catalog.text[], " .
            "'{NULL,NULL,1.1,2.4}'::pg_catalog.float8[], " .
            "'{NULL,NULL}'::pg_catalog.text[], " .
            "'{{NULL,b},{\"{\",\"}\"}}'::pg_catalog.text[], " .
            "'{1.6,8}'::pg_catalog.numeric[]",
            $recip->toSql($this->typeDict)
        );
    }
}

<?php
namespace Ivory\Connection;

use Ivory\Result\QueryResult;
use Ivory\Value\Date;
use Ivory\Value\Quantity;

class ConnConfigTest extends \Ivory\IvoryTestCase
{
    /** @var IConnConfig */
    private $cfg;

    protected function setUp()
    {
        $this->cfg = $this->getIvoryConnection()->getConfig();
        $this->getIvoryConnection()->startTransaction();
    }

    protected function tearDown()
    {
        $this->getIvoryConnection()->rollback();
    }

    public function testSetting()
    {
        $vars = [
            ConfigParam::APPLICATION_NAME, // application_name is processed and cached by pg_parameter_status()
            ConfigParam::SEARCH_PATH, // search_path must be queried directly
        ];
        foreach ($vars as $var) {
            $this->cfg->setForSession($var, 'Ivorytest');
            $this->getIvoryConnection()->commit();
            $this->getIvoryConnection()->startTransaction();
            $this->assertSame('Ivorytest', $this->cfg->get($var));

            $this->cfg->setForSession($var, 'foo');
            $this->assertSame('foo', $this->cfg->get($var));
            $this->getIvoryConnection()->rollback();
            $this->assertSame('Ivorytest', $this->cfg->get($var));
            $this->getIvoryConnection()->startTransaction();

            $this->cfg->setForSession($var, 'foo');
            $this->assertSame('foo', $this->cfg->get($var));
            $this->getIvoryConnection()->commit();
            $this->assertSame('foo', $this->cfg->get($var));
            $this->getIvoryConnection()->startTransaction();

            $this->cfg->setForTransaction($var, 'bar');
            $this->assertSame('bar', $this->cfg->get($var));
            $this->getIvoryConnection()->commit();
            $this->assertSame('foo', $this->cfg->get($var));
            $this->getIvoryConnection()->startTransaction();
        }

        $this->cfg->setForSession(ConfigParam::SEARCH_PATH, 'pg_catalog, public');
    }

    public function testOptionWithQuantity()
    {
        $this->cfg->setForTransaction(ConfigParam::LOCK_TIMEOUT, Quantity::fromValue(15001, 'ms'));
        $this->assertEquals(Quantity::fromValue(15001, 'ms'), $this->cfg->get(ConfigParam::LOCK_TIMEOUT));

        $this->cfg->setForTransaction(ConfigParam::LOCK_TIMEOUT, Quantity::fromValue(15000, 'ms'));
        $this->assertEquals(Quantity::fromValue(15, 's'), $this->cfg->get(ConfigParam::LOCK_TIMEOUT));

        $this->cfg->setForTransaction(ConfigParam::LOCK_TIMEOUT, Quantity::fromValue(10, 's'));
        $this->assertEquals(Quantity::fromValue(10, 's'), $this->cfg->get(ConfigParam::LOCK_TIMEOUT));
    }

    public function testCustomOptions()
    {
        $this->assertNull($this->cfg->get('ivory.foo'));
        $this->cfg->setForTransaction('ivory.foo', 'bar');
        $this->assertSame('bar', $this->cfg->get('ivory.foo'));
    }

    /**
     * Test whether Ivory respects eventual DateStyle changes.
     */
    public function testDateStyle()
    {
        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'ISO');
        /** @var QueryResult $res */
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        $this->assertEquals(Date::fromParts(2016, 3, 5), $tuple['d']);
        $this->assertSame('2016-03-05', $tuple['t']);

        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'SQL, DMY');
        /** @var QueryResult $res */
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        $this->assertEquals(Date::fromParts(2016, 3, 5), $tuple['d']);
        $this->assertSame('05/03/2016', $tuple['t']);

        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'SQL, MDY');
        /** @var QueryResult $res */
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        $this->assertEquals(Date::fromParts(2016, 3, 5), $tuple['d']);
        $this->assertSame('03/05/2016', $tuple['t']);

        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'German');
        /** @var QueryResult $res */
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        $this->assertEquals(Date::fromParts(2016, 3, 5), $tuple['d']);
        $this->assertSame('05.03.2016', $tuple['t']);

        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'Postgres, MDY');
        /** @var QueryResult $res */
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        $this->assertEquals(Date::fromParts(2016, 3, 5), $tuple['d']);
        $this->assertSame('03-05-2016', $tuple['t']);

        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'Postgres, DMY');
        /** @var QueryResult $res */
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        $this->assertEquals(Date::fromParts(2016, 3, 5), $tuple['d']);
        $this->assertSame('05-03-2016', $tuple['t']);

        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'Postgres, YMD'); // unsupported for DateStyle Postgres, MDY is actually used
        /** @var QueryResult $res */
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        $this->assertEquals(Date::fromParts(2016, 3, 5), $tuple['d']);
        $this->assertSame('03-05-2016', $tuple['t']);
    }
}

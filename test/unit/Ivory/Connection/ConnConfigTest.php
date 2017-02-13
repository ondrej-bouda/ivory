<?php
namespace Ivory\Connection;

use Ivory\Exception\StatementException;
use Ivory\Result\QueryResult;
use Ivory\Result\SqlState;
use Ivory\Utils\System;
use Ivory\Value\Date;
use Ivory\Value\Quantity;

class ConnConfigTest extends \Ivory\IvoryTestCase
{
    /** @var ConnConfig */
    private $cfg;

    protected function setUp()
    {
        parent::setUp();

        $this->cfg = $this->getIvoryConnection()->getConfig();
        $this->getIvoryConnection()->startTransaction();
    }

    protected function tearDown()
    {
        $this->getIvoryConnection()->rollback();

        parent::tearDown();
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

        // YMD is unsupported for DateStyle Postgres, MDY is actually used
        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'Postgres, YMD');
        /** @var QueryResult $res */
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        $this->assertEquals(Date::fromParts(2016, 3, 5), $tuple['d']);
        $this->assertSame('03-05-2016', $tuple['t']);
    }

    public function testObservingOutOfTransaction()
    {
        $obsAll = new ConnConfigTestObserver();
        $obsSome = new ConnConfigTestObserver();
        $this->cfg->addObserver($obsAll);
        $this->cfg->addObserver($obsSome, 'Ivory.customized');
        $this->cfg->addObserver($obsSome, [ConfigParam::APPLICATION_NAME, ConfigParam::MONEY_DEC_SEP]);

        $this->getIvoryConnection()->commit();

        try {
            $this->cfg->setForSession('Ivory.customized', 'foo');
            $this->assertSame([['Ivory.customized', 'foo']], $obsAll->fetchObserved());
            $this->assertSame([['Ivory.customized', 'foo']], $obsSome->fetchObserved());

            $this->cfg->setForSession('Ivory.other', 'bar');
            $this->assertSame([['Ivory.other', 'bar']], $obsAll->fetchObserved());
            $this->assertSame([], $obsSome->fetchObserved());

            $this->cfg->setForSession('Ivory.customized', 'baz');
            $this->assertSame([['Ivory.customized', 'baz']], $obsAll->fetchObserved());
            $this->assertSame([['Ivory.customized', 'baz']], $obsSome->fetchObserved());

            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'Ivory');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obsAll->fetchObserved());
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obsSome->fetchObserved());

            $this->cfg->setForSession(ConfigParam::DATE_STYLE, 'Postgres');
            $this->assertSame([[ConfigParam::DATE_STYLE, 'Postgres']], $obsAll->fetchObserved());
            $this->assertSame([], $obsSome->fetchObserved());

            $val = (System::isWindows() ? 'Czech_Czech Republic.1250' : 'cs_CZ.utf8');
            $this->cfg->setForSession(ConfigParam::LC_MONETARY, $val);
            $this->assertSame(
                [[ConfigParam::LC_MONETARY, $val], [ConfigParam::MONEY_DEC_SEP, ',']],
                $obsAll->fetchObserved()
            );
            $this->assertSame([[ConfigParam::MONEY_DEC_SEP, ',']], $obsSome->fetchObserved());

            $val = (System::isWindows() ? 'English_US' : 'en_US.utf8');
            $this->cfg->setForSession(ConfigParam::LC_MONETARY, $val);
            $this->assertSame(
                [[ConfigParam::LC_MONETARY, $val], [ConfigParam::MONEY_DEC_SEP, '.']],
                $obsAll->fetchObserved()
            );
            $this->assertSame([[ConfigParam::MONEY_DEC_SEP, '.']], $obsSome->fetchObserved());

            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'Foo');
            $this->assertSame(
                [], $obsAll->fetchObserved(),
                'IConnConfig::setTransaction() should have no effect outside transaction'
            );
            $this->assertSame(
                [], $obsSome->fetchObserved(),
                'IConnConfig::setTransaction() should have no effect outside transaction'
            );

            $this->cfg->resetAll();
            $this->assertSame([ConnConfigTestObserver::RESET], $obsAll->fetchObserved());
            $this->assertSame([ConnConfigTestObserver::RESET], $obsSome->fetchObserved());
        }
        finally {
            $this->getIvoryConnection()->startTransaction();
        }
    }

    public function testObservingInTransaction()
    {
        $conn = $this->getIvoryConnection();
        $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'ConnConfigTest');
        $conn->commit();

        $this->clearPreparedTransaction('t');

        $obs = new ConnConfigTestObserver();
        $this->cfg->addObserver($obs);

        try {
            // session-wide parameter changes
            $conn->startTransaction();
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'Ivory');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());
            $conn->commit();
            $this->assertSame([], $obs->fetchObserved());

            $conn->startTransaction();
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'foo');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $conn->rollback();
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());

            // transaction-wide parameter changes
            $conn->startTransaction();
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'foo');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $conn->commit();
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());

            $conn->startTransaction();
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'foo');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $conn->rollback();
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());

            // savepoints
            $conn->startTransaction();
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'foo');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $conn->savepoint('s1');
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'bar');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'bar']], $obs->fetchObserved());
            $conn->savepoint('s2');
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'baz');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'baz']], $obs->fetchObserved());
            $conn->rollbackToSavepoint('s1');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $conn->commit();
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());

            // prepared transactions - session-wide changes
            $conn->startTransaction();
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'foo');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $conn->prepareTransaction('t');
            $this->assertSame([], $obs->fetchObserved());
            $conn->rollbackPreparedTransaction('t');

            // prepared transactions - transaction-wide changes
            $conn->startTransaction();
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'bar');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'bar']], $obs->fetchObserved());
            $conn->prepareTransaction('t');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $conn->rollbackPreparedTransaction('t');

            // reset all - notify about the reset again upon rolling back the transaction
            $conn->startTransaction();
            $this->cfg->resetAll();
            $this->assertSame([ConnConfigTestObserver::RESET], $obs->fetchObserved());
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'baz');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'baz']], $obs->fetchObserved());
            $conn->rollback();
            $this->assertSame([ConnConfigTestObserver::RESET], $obs->fetchObserved());

            // reset all - only notify about the reset again upon rolling back to savepoint since which the reset was made
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'Ivory');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());
            $conn->startTransaction();
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'I');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'I']], $obs->fetchObserved());
            $conn->savepoint('s1');
            $this->cfg->resetAll();
            $this->assertSame([ConnConfigTestObserver::RESET], $obs->fetchObserved());
            $conn->savepoint('s2');
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'bar');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'bar']], $obs->fetchObserved());
            $conn->rollbackToSavepoint('s2');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, '']], $obs->fetchObserved());
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'bar');
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'bar']], $obs->fetchObserved());
            $conn->rollbackToSavepoint('s1');
            $this->assertSame([ConnConfigTestObserver::RESET], $obs->fetchObserved());
            $conn->rollback();
            $this->assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());
        }
        finally {
            $this->clearPreparedTransaction('t');
            if (!$conn->inTransaction()) {
                $conn->startTransaction();
            }
        }
    }

    private function clearPreparedTransaction($name)
    {
        $conn = $this->getIvoryConnection();
        try {
            if ($conn->inTransaction()) {
                $conn->rollback();
            }
            $conn->rollbackPreparedTransaction($name);
        } catch (StatementException $e) {
            if ($e->getSqlState()->getCode() != SqlState::UNDEFINED_OBJECT) {
                throw $e;
            }
        }
    }
}

class ConnConfigTestObserver implements IConfigObserver
{
    const RESET = '__reset__';

    private $observed = [];

    /**
     * @return array list of events received since the previous call to this method
     */
    public function fetchObserved()
    {
        $o = $this->observed;
        $this->observed = [];
        return $o;
    }

    public function handlePropertyChange($propertyName, $newValue)
    {
        $this->observed[] = [$propertyName, $newValue];
    }

    public function handlePropertiesReset(IConnConfig $connConfig)
    {
        $this->observed[] = self::RESET;
    }
}

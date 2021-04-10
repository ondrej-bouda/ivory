<?php
declare(strict_types=1);
namespace Ivory\Connection\Config;

use Ivory\Connection\ITxHandle;
use Ivory\Exception\StatementException;
use Ivory\IvoryTestCase;
use Ivory\Lang\Sql\SqlState;
use Ivory\Utils\System;
use Ivory\Value\Date;
use Ivory\Value\Quantity;

class ConnConfigTest extends IvoryTestCase
{
    /** @var ConnConfig */
    private $cfg;
    /** @var ITxHandle */
    private $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->cfg = $this->getIvoryConnection()->getConfig();
        $this->transaction = $this->getIvoryConnection()->startTransaction();
    }

    protected function tearDown(): void
    {
        $this->transaction->rollback();

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
            $this->transaction->commit();
            $this->transaction = $this->getIvoryConnection()->startTransaction();
            self::assertSame('Ivorytest', $this->cfg->get($var));

            $this->cfg->setForSession($var, 'foo');
            self::assertSame('foo', $this->cfg->get($var));
            $this->transaction->rollback();
            self::assertSame('Ivorytest', $this->cfg->get($var));
            $this->transaction = $this->getIvoryConnection()->startTransaction();

            $this->cfg->setForSession($var, 'foo');
            self::assertSame('foo', $this->cfg->get($var));
            $this->transaction->commit();
            self::assertSame('foo', $this->cfg->get($var));
            $this->transaction = $this->getIvoryConnection()->startTransaction();

            $this->cfg->setForTransaction($var, 'bar');
            self::assertSame('bar', $this->cfg->get($var));
            $this->transaction->commit();
            self::assertSame('foo', $this->cfg->get($var));
            $this->transaction = $this->getIvoryConnection()->startTransaction();
        }

        $this->cfg->setForSession(ConfigParam::SEARCH_PATH, 'pg_catalog, public');
    }

    public function testOptionWithQuantity()
    {
        $this->cfg->setForTransaction(ConfigParam::LOCK_TIMEOUT, Quantity::fromValue(15001, 'ms'));
        self::assertEquals(Quantity::fromValue(15001, 'ms'), $this->cfg->get(ConfigParam::LOCK_TIMEOUT));

        $this->cfg->setForTransaction(ConfigParam::LOCK_TIMEOUT, Quantity::fromValue(15000, 'ms'));
        self::assertEquals(Quantity::fromValue(15, 's'), $this->cfg->get(ConfigParam::LOCK_TIMEOUT));

        $this->cfg->setForTransaction(ConfigParam::LOCK_TIMEOUT, Quantity::fromValue(10, 's'));
        self::assertEquals(Quantity::fromValue(10, 's'), $this->cfg->get(ConfigParam::LOCK_TIMEOUT));
    }

    public function testCustomOptions()
    {
        self::assertNull($this->cfg->get('ivory.foo'));
        $this->cfg->setForTransaction('ivory.foo', 'bar');
        self::assertSame('bar', $this->cfg->get('ivory.foo'));
    }

    /**
     * Test whether Ivory respects eventual DateStyle changes.
     */
    public function testDateStyle()
    {
        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'ISO');
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        self::assertEquals(Date::fromParts(2016, 3, 5), $tuple->d);
        self::assertSame('2016-03-05', $tuple->t);

        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'SQL, DMY');
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        self::assertEquals(Date::fromParts(2016, 3, 5), $tuple->d);
        self::assertSame('05/03/2016', $tuple->t);

        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'SQL, MDY');
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        self::assertEquals(Date::fromParts(2016, 3, 5), $tuple->d);
        self::assertSame('03/05/2016', $tuple->t);

        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'German');
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        self::assertEquals(Date::fromParts(2016, 3, 5), $tuple->d);
        self::assertSame('05.03.2016', $tuple->t);

        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'Postgres, MDY');
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        self::assertEquals(Date::fromParts(2016, 3, 5), $tuple->d);
        self::assertSame('03-05-2016', $tuple->t);

        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'Postgres, DMY');
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        self::assertEquals(Date::fromParts(2016, 3, 5), $tuple->d);
        self::assertSame('05-03-2016', $tuple->t);

        // YMD is unsupported for DateStyle Postgres, MDY is actually used
        $this->cfg->setForTransaction(ConfigParam::DATE_STYLE, 'Postgres, YMD');
        $res = $this->getIvoryConnection()->rawQuery("SELECT '2016-03-05'::DATE AS d, '2016-03-05'::DATE::TEXT AS t");
        $tuple = $res->tuple();
        self::assertEquals(Date::fromParts(2016, 3, 5), $tuple->d);
        self::assertSame('03-05-2016', $tuple->t);
    }

    public function testObservingOutOfTransaction()
    {
        $obsAll = new ConnConfigTestObserver();
        $obsSome = new ConnConfigTestObserver();
        $this->cfg->addObserver($obsAll);
        $this->cfg->addObserver($obsSome, 'Ivory.customized');
        $this->cfg->addObserver($obsSome, [ConfigParam::APPLICATION_NAME, ConfigParam::MONEY_DEC_SEP]);

        $this->transaction->commit();

        try {
            $this->cfg->setForSession('Ivory.customized', 'foo');
            self::assertSame([['Ivory.customized', 'foo']], $obsAll->fetchObserved());
            self::assertSame([['Ivory.customized', 'foo']], $obsSome->fetchObserved());

            $this->cfg->setForSession('Ivory.other', 'bar');
            self::assertSame([['Ivory.other', 'bar']], $obsAll->fetchObserved());
            self::assertSame([], $obsSome->fetchObserved());

            $this->cfg->setForSession('Ivory.customized', 'baz');
            self::assertSame([['Ivory.customized', 'baz']], $obsAll->fetchObserved());
            self::assertSame([['Ivory.customized', 'baz']], $obsSome->fetchObserved());

            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'Ivory');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obsAll->fetchObserved());
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obsSome->fetchObserved());

            $this->cfg->setForSession(ConfigParam::DATE_STYLE, 'Postgres');
            self::assertSame([[ConfigParam::DATE_STYLE, 'Postgres']], $obsAll->fetchObserved());
            self::assertSame([], $obsSome->fetchObserved());

            $val = (System::isWindows() ? 'Czech_Czechia.1250' : 'cs_CZ.utf8');
            $this->cfg->setForSession(ConfigParam::LC_MONETARY, $val);
            self::assertSame(
                [[ConfigParam::LC_MONETARY, $val], [ConfigParam::MONEY_DEC_SEP, ',']],
                $obsAll->fetchObserved()
            );
            self::assertSame([[ConfigParam::MONEY_DEC_SEP, ',']], $obsSome->fetchObserved());

            $val = (System::isWindows() ? 'English_US' : 'en_US.utf8');
            $this->cfg->setForSession(ConfigParam::LC_MONETARY, $val);
            self::assertSame(
                [[ConfigParam::LC_MONETARY, $val], [ConfigParam::MONEY_DEC_SEP, '.']],
                $obsAll->fetchObserved()
            );
            self::assertSame([[ConfigParam::MONEY_DEC_SEP, '.']], $obsSome->fetchObserved());

            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'Foo');
            self::assertSame(
                [], $obsAll->fetchObserved(),
                'IConnConfig::setTransaction() should have no effect outside transaction'
            );
            self::assertSame(
                [], $obsSome->fetchObserved(),
                'IConnConfig::setTransaction() should have no effect outside transaction'
            );

            $this->cfg->resetAll();
            self::assertSame([ConnConfigTestObserver::RESET], $obsAll->fetchObserved());
            self::assertSame([ConnConfigTestObserver::RESET], $obsSome->fetchObserved());
        } finally {
            $this->transaction = $this->getIvoryConnection()->startTransaction();
        }
    }

    public function testObservingInTransaction()
    {
        $conn = $this->getIvoryConnection();
        $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'ConnConfigTest');
        $this->transaction->commit();

        $this->clearPreparedTransaction('t');

        $obs = new ConnConfigTestObserver();
        $this->cfg->addObserver($obs);

        try {
            // session-wide parameter changes
            $tx = $conn->startTransaction();
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'Ivory');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());
            $tx->commit();
            self::assertSame([], $obs->fetchObserved());

            $tx = $conn->startTransaction();
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'foo');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $tx->rollback();
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());

            // transaction-wide parameter changes
            $tx = $conn->startTransaction();
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'foo');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $tx->commit();
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());

            $tx = $conn->startTransaction();
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'foo');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $tx->rollback();
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());

            // savepoints
            $tx = $conn->startTransaction();
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'foo');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $tx->savepoint('s1');
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'bar');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'bar']], $obs->fetchObserved());
            $tx->savepoint('s2');
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'baz');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'baz']], $obs->fetchObserved());
            $tx->rollbackToSavepoint('s1');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $tx->commit();
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());

            // prepared transactions - session-wide changes
            $tx = $conn->startTransaction();
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'foo');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $tx->prepareTransaction('t');
            self::assertSame([], $obs->fetchObserved());
            $conn->rollbackPreparedTransaction('t');

            // prepared transactions - transaction-wide changes
            $tx = $conn->startTransaction();
            $this->cfg->setForTransaction(ConfigParam::APPLICATION_NAME, 'bar');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'bar']], $obs->fetchObserved());
            $tx->prepareTransaction('t');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'foo']], $obs->fetchObserved());
            $conn->rollbackPreparedTransaction('t');

            // reset all - notify about the reset again upon rolling back the transaction
            $tx = $conn->startTransaction();
            $this->cfg->resetAll();
            self::assertSame([ConnConfigTestObserver::RESET], $obs->fetchObserved());
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'baz');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'baz']], $obs->fetchObserved());
            $tx->rollback();
            self::assertSame([ConnConfigTestObserver::RESET], $obs->fetchObserved());

            // reset all - only notify about the reset again upon rolling back to savepoint since which the reset was
            // made
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'Ivory');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());
            $tx = $conn->startTransaction();
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'I');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'I']], $obs->fetchObserved());
            $tx->savepoint('s1');
            $this->cfg->resetAll();
            self::assertSame([ConnConfigTestObserver::RESET], $obs->fetchObserved());
            $tx->savepoint('s2');
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'bar');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'bar']], $obs->fetchObserved());
            $tx->rollbackToSavepoint('s2');
            self::assertSame([[ConfigParam::APPLICATION_NAME, '']], $obs->fetchObserved());
            $this->cfg->setForSession(ConfigParam::APPLICATION_NAME, 'bar');
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'bar']], $obs->fetchObserved());
            $tx->rollbackToSavepoint('s1');
            self::assertSame([ConnConfigTestObserver::RESET], $obs->fetchObserved());
            $tx->rollback();
            self::assertSame([[ConfigParam::APPLICATION_NAME, 'Ivory']], $obs->fetchObserved());
        } finally {
            $this->clearPreparedTransaction('t');
            if (!$this->transaction->isOpen()) {
                $this->transaction = $conn->startTransaction();
            }
        }
    }

    /** @noinspection PhpSameParameterValueInspection */
    private function clearPreparedTransaction($name)
    {
        $conn = $this->getIvoryConnection();
        try {
            if ($conn->inTransaction()) {
                $this->transaction->rollback();
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

    public function handlePropertyChange(string $propertyName, $newValue): void
    {
        $this->observed[] = [$propertyName, $newValue];
    }

    public function handlePropertiesReset(IConnConfig $connConfig): void
    {
        $this->observed[] = self::RESET;
    }
}

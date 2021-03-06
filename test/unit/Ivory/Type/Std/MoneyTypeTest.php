<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Connection\Config\ConfigParam;
use Ivory\Connection\ITxHandle;
use Ivory\IvoryTestCase;
use Ivory\Utils\System;
use Ivory\Value\Money;

class MoneyTypeTest extends IvoryTestCase
{
    /** @var ITxHandle */
    private $transaction;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transaction = $this->getIvoryConnection()->startTransaction();
        $val = (System::isWindows() ? 'Czech_Czechia.1250' : 'cs_CZ.utf8');
        $this->getIvoryConnection()->getConfig()->setForTransaction(ConfigParam::LC_MONETARY, $val);
    }

    protected function tearDown(): void
    {
        $this->transaction->rollback();

        parent::tearDown();
    }

    public function testDelimiter()
    {
        $conn = $this->getIvoryConnection();

        $r = $conn->rawQuery('SELECT 12345.678::money'); // formatted as "$12,345.68"
        self::assertEquals(Money::fromNumber(12345.68, 'Kč'), $r->value());

        $val = (System::isWindows() ? 'English_US' : 'en_US.utf8');
        $conn->getConfig()->setForTransaction(ConfigParam::LC_MONETARY, $val);
        $r = $conn->rawQuery('SELECT 12345.678::money'); // formatted as "$12,345.68"
        self::assertEquals(Money::fromNumber(12345.68, '$'), $r->value());

        if (System::isWindows()) {
            $monet = 'Japanese_Japan.932';
            $yenSign = '\\';
        } else {
            $monet = 'ja_JP.UTF-8';
            $yenSign = '￥';
        }
        $conn->getConfig()->setForTransaction(ConfigParam::LC_MONETARY, $monet);
        $r = $conn->rawQuery('SELECT 12345.678::money'); // formatted as "\12,346"
        self::assertEquals(Money::fromNumber(12346, $yenSign), $r->value());
    }
}

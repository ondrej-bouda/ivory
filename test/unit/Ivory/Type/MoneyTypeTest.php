<?php
namespace Ivory\Type;

use Ivory\Connection\ConfigParam;
use Ivory\IvoryTestCase;
use Ivory\Result\IQueryResult;
use Ivory\Utils\System;
use Ivory\Value\Money;

class MoneyTypeTest extends IvoryTestCase
{
    protected function setUp()
    {
        parent::setUp();

        $this->getIvoryConnection()->startTransaction();
        $val = (System::isWindows() ? 'Czech_Czech Republic.1250' : 'cs_CZ.utf8');
        $this->getIvoryConnection()->getConfig()->setForTransaction(ConfigParam::LC_MONETARY, $val);
    }

    protected function tearDown()
    {
        $this->getIvoryConnection()->rollback();

        parent::tearDown();
    }

    public function testDelimiter()
    {
        $conn = $this->getIvoryConnection();

        /** @var IQueryResult $r */
        $r = $conn->rawQuery('SELECT 12345.678::money'); // formatted as "$12,345.68"
        $this->assertEquals(Money::fromNumber(12345.68, 'Kč'), $r->value());

        $val = (System::isWindows() ? 'English_US' : 'en_US.utf8');
        $conn->getConfig()->setForTransaction(ConfigParam::LC_MONETARY, $val);
        $r = $conn->rawQuery('SELECT 12345.678::money'); // formatted as "$12,345.68"
        $this->assertEquals(Money::fromNumber(12345.68, '$'), $r->value());

        if (System::isWindows()) {
            $monet = 'Japanese_Japan.932';
            $yenSign = '\\';
        } else {
            $monet = 'ja_JP.UTF-8';
            $yenSign = '￥';
        }
        $conn->getConfig()->setForTransaction(ConfigParam::LC_MONETARY, $monet);
        $r = $conn->rawQuery('SELECT 12345.678::money'); // formatted as "\12,346"
        $this->assertEquals(Money::fromNumber(12346, $yenSign), $r->value());
    }
}

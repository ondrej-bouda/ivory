<?php
namespace Ivory\Type;

use Ivory\IvoryTestCase;
use Ivory\Result\IQueryResult;
use Ivory\Utils\System;
use Ivory\Value\Money;

class MoneyTypeTest extends IvoryTestCase
{
    protected function setUp()
    {
        $this->getIvoryConnection()->startTransaction();
        $val = (System::isWindows() ? 'Czech_Czech Republic.1250' : 'cs_CZ.utf8');
        $this->getIvoryConnection()->getConfig()->setForTransaction('lc_monetary', $val);
    }

    protected function tearDown()
    {
        $this->getIvoryConnection()->rollback();
    }

    public function testDelimiter()
    {
        $conn = $this->getIvoryConnection();

        /** @var IQueryResult $r */
        $r = $conn->rawQuery('SELECT 12345.678::money'); // formatted as "$12,345.68"
        $this->assertEquals(Money::fromNumber(12345.68, 'Kč'), $r->value());

        $val = (System::isWindows() ? 'English_US' : 'en_US.utf8');
        $conn->getConfig()->setForTransaction('lc_monetary', $val);
        $r = $conn->rawQuery('SELECT 12345.678::money'); // formatted as "$12,345.68"
        $this->assertEquals(Money::fromNumber(12345.68, '$'), $r->value());

        $val = (System::isWindows() ? 'Japanese_Japan' : 'ja_JP.UTF-8');
        $conn->getConfig()->setForTransaction('lc_monetary', $val);
        $r = $conn->rawQuery('SELECT 12345.678::money'); // formatted as "\12,346"
        $this->assertEquals(Money::fromNumber(12346, '\\'), $r->value());
    }
}

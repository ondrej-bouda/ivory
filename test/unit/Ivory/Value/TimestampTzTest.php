<?php
declare(strict_types=1);
namespace Ivory\Value;

class TimestampTzTest extends \PHPUnit\Framework\TestCase
{
    public function testNow()
    {
        // there is a little chance of hitting the following code exactly at time with no microseconds
        $hitNonZeroMicrosec = false;
        for ($i = 0; $i < 10; $i++) {
            $start = time();
            $dt = TimestampTz::now();
            $end = time();
            $this->assertLessThanOrEqual($start, $dt->toUnixTimestamp());
            $this->assertLessThanOrEqual($dt->toUnixTimestamp(), $end);
            // the precision should be greater than just mere seconds
            if ($dt->format('u') != 0) {
                $hitNonZeroMicrosec = true;
                break; // OK - non-zero fractional part
            }
        }
        $this->assertTrue($hitNonZeroMicrosec);
    }
}

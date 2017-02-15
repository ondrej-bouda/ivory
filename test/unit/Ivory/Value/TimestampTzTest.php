<?php
namespace Ivory\Value;

class TimestampTzTest extends \PHPUnit_Framework_TestCase
{
    public function testNow()
    {
        // there is a little chance of hitting the following code exactly at time with no microseconds
        $hitNonZeroMicrosec = false;
        for ($i = 0; $i < 10; $i++) {
            $start = time();
            $dt = TimestampTz::now();
            $end = time();
            $this->assertLessThanOrEqual($dt->toUnixTimestamp(), $start);
            $this->assertLessThanOrEqual($end, $dt->toUnixTimestamp());
            // the precision should be greater than just mere seconds
            if ($dt->format('u') != 0) {
                $hitNonZeroMicrosec = true;
                break; // OK - non-zero fractional part
            }
        }
        $this->assertTrue($hitNonZeroMicrosec);
    }
}

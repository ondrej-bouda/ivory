<?php
declare(strict_types=1);
namespace Ivory\Value;

use PHPUnit\Framework\TestCase;

class TimestampTzTest extends TestCase
{
    public function testNow()
    {
        // there is a little chance of hitting the following code exactly at time with no microseconds
        $hitNonZeroMicrosec = false;
        for ($i = 0; $i < 10; $i++) {
            $start = time();
            $dt = TimestampTz::now();
            $end = time();
            self::assertLessThanOrEqual($start, $dt->toUnixTimestamp());
            self::assertLessThanOrEqual($dt->toUnixTimestamp(), $end);
            // the precision should be greater than just mere seconds
            if ($dt->format('u') != 0) {
                $hitNonZeroMicrosec = true;
                break; // OK - non-zero fractional part
            }
        }
        self::assertTrue($hitNonZeroMicrosec);
    }
}

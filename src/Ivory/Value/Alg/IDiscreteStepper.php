<?php
declare(strict_types=1);
namespace Ivory\Value\Alg;

/**
 * An object performing discrete steps to preceding and following values.
 *
 * Note this is rather a technical interface, needed due to the lack of extension methods in PHP.
 */
interface IDiscreteStepper
{
    /**
     * Perform a step to the preceding or following value.
     *
     * @param int $delta either -1 or +1; for other values, the result is undefined
     * @param mixed $value a value of a discrete type
     * @return mixed value immediately preceding or following <tt>$value</tt>, if <tt>$direction</tt> is -1 or +1,
     *                 respectively;
     *               note that in some special cases, the <tt>$value</tt> itself may be returned (e.g., for the date
     *                 <i>infinity</i>)
     * @throws \InvalidArgumentException if <tt>$value</tt> is <tt>null</tt> or of a non-supported type
     */
    function step(int $delta, $value);
}

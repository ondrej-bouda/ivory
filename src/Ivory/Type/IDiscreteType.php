<?php
namespace Ivory\Type;

interface IDiscreteType extends ITotallyOrderedType
{
    /**
     * @param int $delta either -1 or +1; for other values, the result is undefined
     * @param mixed $value a value of this type
     * @return mixed value immediately preceding or following <tt>$value</tt>, if <tt>$direction</tt> is -1 or +1,
     *                 respectively;
     *               note that in some special cases, e.g., for the date infinity, the <tt>$value</tt> itself may be
     *                 returned;
     *               <tt>null</tt> iff <tt>$value</tt> is <tt>null</tt>
     */
    function step($delta, $value);
}

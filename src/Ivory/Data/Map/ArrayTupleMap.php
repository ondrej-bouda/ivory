<?php
namespace Ivory\Data\Map;

/**
 * {@inheritdoc}
 *
 * This implementation uses a plain PHP array to store the mapping. Thus, it is limited to only store string or integer
 * keys.
 */
class ArrayTupleMap implements \IteratorAggregate, IWritableTupleMap
{
    use ArrayMapMacros;

    //region ArrayMapMacros

    protected function isNestedMap($entry)
    {
        return ($entry instanceof ITupleMap);
    }

    //endregion
}

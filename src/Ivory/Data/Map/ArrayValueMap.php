<?php
namespace Ivory\Data\Map;

/**
 * {@inheritdoc}
 *
 * This implementation uses a plain PHP array to store the mapping. Thus, it is limited to only store string or integer
 * keys.
 */
class ArrayValueMap implements \IteratorAggregate, IWritableValueMap
{
    use ArrayMapMacros;

    //region ArrayMapMacros

    protected function isNestedMap($entry): bool
    {
        return ($entry instanceof IValueMap);
    }

    //endregion
}

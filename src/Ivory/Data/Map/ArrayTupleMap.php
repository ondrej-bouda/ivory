<?php
/** @noinspection PhpInappropriateInheritDocUsageInspection PhpStorm bug WI-54015 */
declare(strict_types=1);
namespace Ivory\Data\Map;

/**
 * A hashmap of tuples.
 *
 * {@inheritdoc}
 *
 * This implementation uses a plain PHP array to store the mapping. Thus, it is limited to only store string or integer
 * keys.
 */
class ArrayTupleMap implements \IteratorAggregate, IWritableTupleMap
{
    use ArrayMapMacros;

    //region ArrayMapMacros

    protected function isNestedMap($entry): bool
    {
        return ($entry instanceof ITupleMap);
    }

    //endregion
}

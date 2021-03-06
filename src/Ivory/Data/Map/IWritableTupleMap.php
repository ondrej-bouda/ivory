<?php
declare(strict_types=1);
namespace Ivory\Data\Map;

use Ivory\Relation\ITuple;

interface IWritableTupleMap extends ITupleMap
{
    /**
     * Puts an entry (either an {@link ITuple} or {@link ITupleMap}) into the map under a given key.
     *
     * If there already is an entry under <tt>$key</tt> it is removed and <tt>$entry</tt> is stored instead of it.
     *
     * @param mixed $key
     * @param ITuple|ITupleMap $entry
     */
    function put($key, $entry): void;

    /**
     * Puts an entry (either an {@link ITuple} or {@link ITupleMap}) into the map under a given key unless there already
     * is one under the same key.
     *
     * @param mixed $key
     * @param ITuple|ITupleMap $entry
     * @return bool <tt>true</tt> if there was no entry under <tt>$key</tt> and thus <tt>$entry</tt> has been put into
     *                the map, or <tt>false</tt> if there already was an entry and thus this was a no-op
     */
    function putIfNotExists($key, $entry): bool;

    /**
     * Removes a map entry (either an {@link ITuple} or {@link ITupleMap}) under a given key or series of keys.
     *
     * @param string|int ...$key
     * @return bool <tt>true</tt> if the entry has been removed, <tt>false</tt> if there was no such entry
     */
    function remove(...$key): bool;
}

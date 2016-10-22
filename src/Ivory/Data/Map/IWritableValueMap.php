<?php
namespace Ivory\Data\Map;

interface IWritableValueMap extends IValueMap
{
    /**
     * Puts an entry (either a value or an {@link IValueMap}) into the map under a given key.
     *
     * If there already is an entry under <tt>$key</tt> it is removed and <tt>$entry</tt> is stored instead of it.
     *
     * @param mixed $key
     * @param mixed|IValueMap $entry
     */
    function put($key, $entry);

    /**
     * Puts an entry (either a value or an {@link IValueMap}) into the map under a given key unless there already
     * is one under the same key.
     *
     * @param mixed $key
     * @param mixed|IValueMap $entry
     * @return bool <tt>true</tt> if there was no entry under <tt>$key</tt> and thus <tt>$entry</tt> has been put into
     *                the map, or <tt>false</tt> if there already was an entry and thus this was a no-op
     */
    function putIfNotExists($key, $entry);

    /**
     * Removes a map entry (either a value or an {@link IValueMap}) under a given key or series of keys.
     *
     * @param array ...$key
     * @return bool <tt>true</tt> if the entry has been removed, <tt>false</tt> if there was no such entry
     */
    function remove(...$key);
}

<?php
namespace Ivory\Connection;

interface ICacheControl
{
    /**
     * Cache an object upon this connection until disconnected.
     *
     * The object will be stored immediately.
     *
     * @param string $cacheKey key to cache the object under; relevant for later identification when retrieving
     * @param mixed $object the object to cache
     * @return bool whether the object has been cached successfully
     */
    function cacheForSession(string $cacheKey, $object): bool;

    /**
     * Cache an object upon this connection permanently.
     *
     * The cached object will (if not dropped by the cache) be returned by {@link getCached()} if connected again to a
     * database of the same name on the same host, having the same pg_database.oid, using the same Ivory version.
     *
     * The object will be stored immediately.
     *
     * @param string $cacheKey key to cache the object under; relevant for later identification when retrieving
     * @param mixed $object the object to cache
     * @return bool whether the object has been cached successfully
     */
    function cachePermanently(string $cacheKey, $object): bool;

    /**
     * Loads an item cached upon this connection.
     *
     * The items gets loaded regardless of the storage durability - both session and permanent caches are searched, the
     * former being preferred.
     *
     * @param string $cacheKey key to get the cached object for
     * @return mixed the cache item, or <tt>null</tt> if no such item is cached or no caching mechanism is set up
     */
    function getCached(string $cacheKey);

    /**
     * Flush the cache associated with this connection.
     *
     * Cached objects stored both for this session and permanently will be flushed. As regards to the latter, the same
     * rules apply for identifying the objects related with this connection as for {@link cachePermanently()}.
     *
     * @param string|null $cacheKey key of object to selectively flush, or <tt>null</tt> to flush all objects
     * @return bool
     */
    function flushCache(string $cacheKey = null): bool;

    /**
     * Flush all cached objects associated with any Ivory connections.
     *
     * @return bool whether the operation has been successful, i.e., no objects remain in the cache for any connection
     */
    function flushAnyIvoryCaches(): bool;
}

<?php
declare(strict_types=1);
namespace Ivory\Cache;

/**
 * Caching functionality for Ivory.
 *
 * @todo Consider checking the target database OID and server version. That requires the connection to be established,
 *       however, and would not allow Ivory to read cache until the connection is ready. Therefore, consider a way of
 *       ex-post validation of cached items, dropping them and removing from cache if OID or server version differs.
 *       Use pg_version($resource)['server'].
 */
interface ICacheControl
{
    /**
     * @return bool whether a cache implementation has been set up, either at the connection or global level
     */
    function isCacheEnabled(): bool;

    /**
     * Cache an object upon this connection permanently.
     *
     * The cached object will (if not dropped by the cache) be returned by {@link getCached()} if connected again to a
     * database of the same name on the same host, using the same Ivory version.
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
     * @param string $cacheKey key to get the cached object for
     * @return mixed the cached object, or <tt>null</tt> if no such item is cached or no caching mechanism is set up
     */
    function getCached(string $cacheKey);

    /**
     * Flush a cached object associated with this connection.
     *
     * @param string|null $cacheKey key of object to flush
     * @return bool whether the operation has been successful
     */
    function flushCache(string $cacheKey): bool;
}

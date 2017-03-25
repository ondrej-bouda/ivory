<?php
namespace Ivory\Connection;

// TODO: consider making the pg_database.oid check optional - especially so that the dictionary might get loaded during connecting to the database
class CacheControl implements ICacheControl
{
    const CACHE_FILE = __DIR__ . '/../../../dict.cache'; // FIXME: remove, just a quick&dirty implementation

    public function cacheForSession(string $cacheKey, $object): bool
    {
        throw new \Ivory\Exception\NotImplementedException(); // TODO
    }

    public function cachePermanently(string $cacheKey, $object): bool
    {
//        return false;

        // FIXME
        $serialized = serialize($object);
        file_put_contents(self::CACHE_FILE, $serialized, LOCK_EX);
        return true;
    }

    public function getCached(string $cacheKey)
    {
//        return null;

        if (file_exists(self::CACHE_FILE)) {
            $serialized = file_get_contents(self::CACHE_FILE);
            $object = @unserialize($serialized);
            if ($object !== false) {
                return $object;
            }
        }

        return null;
    }

    public function flushCache(string $cacheKey = null): bool
    {
        return @unlink(self::CACHE_FILE); // FIXME
    }

    public function flushAnyIvoryCaches(): bool
    {
        return @unlink(self::CACHE_FILE); // FIXME
    }
}

<?php
namespace Ivory\Relation;

/**
 * A data processor which caches some data, e.g., for performance reasons.
 *
 * The implementing class should document which are the volatile parts being cached.
 * Through this interface, it is possible to control the cache explicitly.
 */
interface ICachingDataProcessor
{
    /**
     * Populates the whole cache, if not already populated.
     *
     * This method guarantees all the volatile data to be computed. After populating this processor, the external
     * sources for the volatile data are not needed anymore (unless {@link flush()}ed).
     *
     * Note that it is usually unnecessary to call this method as the data are fetched and cached automatically upon
     * serving the requests for them.
     */
    function populate(): void;

    /**
     * Flushes any volatile data already cached.
     */
    function flush(): void;
}

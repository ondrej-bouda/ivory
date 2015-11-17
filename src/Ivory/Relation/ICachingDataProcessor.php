<?php
namespace Ivory\Relation;

/**
 * A data processor which caches the data, e.g. for performance reasons.
 */
interface ICachingDataProcessor extends IDataProcessor
{
    /**
     * Reads all data from the source and caches them, if not cached already.
     *
     * After populating this processor, the data source is no longer needed (unless {@link flush()}ed).
     *
     * Note that it is usually unnecessary to call this method as the data are fetched and cached automatically upon
     * serving the requests for them.
     */
    function populate();
    /**
     * Flushes any data already taken and cached from the source, as well as any auxiliary caches used by this object.
     *
     * This method is useful, e.g., for re-issuing a database query, or re-evaluating volatile parts of
     * {@link IRelation relation} definitions, such as filters or dynamically projected columns.
     */
    function flush();
}

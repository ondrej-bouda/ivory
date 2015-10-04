<?php
namespace Ivory\Connection;

/**
 * Connection config enhanced with client-side caching of known values.
 *
 * For details on basic functionality, see the {@link IConnConfig} documentation.
 *
 * On top of {@link IConnConfig} features, caching of configuration parameter values is supported, and enabled by
 * default. That may be customized both globally and for individual configuration parameters using
 * {@link IConnConfig::disableCaching()} and {@link IConnConfig::enableCaching()} methods. The cache may also be
 * {@link IConnConfig::invalidateCache() invalidated}, forcing to read the fresh values once again from the database.
 * Note that changing a setting is transactional, i.e., the original value is restored if the transaction is rolled back
 * (either to a savepoint or as the whole transaction).
 *
 * For the cache to work correctly, it is necessary to change the configuration parameter values exclusively through
 * this class, using the {@link IConnConfig::setForSession()} or {@link IConnConfig::setForTransaction()} method.
 * Otherwise, the cache might be stale. To prevent the problem, caching might be
 * {@link ICachingConnConfig::disableCaching() disabled} or {@link ICachingConnConfig::invalidateCache() invalidated}
 * for specific parameters.
 *
 * Cached configuration parameters may be written (and subsequently read from the cache) even before the connection is
 * actually established. The values get applied once the connection is up. Note, however, that reading any cached option
 * without writing to it first, as well as writing any non-cached option, leads to an {@link InvalidStateException} if
 * no connection is alive. Also note that no values survive {@link IConnection::close() closing the connection} - after
 * the connection gets closed, all cached values are invalidated and writing them leads to the same pre-caching
 * behaviour as before the first connecting. The only attribute which persists across the connection re-opening is the
 * caching settings, i.e., whether the caching is enabled (and for which configuration parameters).
 */
interface ICachingConnConfig extends IConnConfig
{
	/**
	 * Invalidates the cache of configuration values. Next time a configuration option is read, its fresh value is
	 * queried from the database.
	 *
	 * @param string|string[] one or more names of parameters the cached values of which to invalidate;
	 *                        <tt>null</tt> invalidates everything
	 */
	function invalidateCache($paramName = null);;

	/**
	 * Disables caching of values of a given configuration parameter, or of any configuration parameter.
	 *
	 * This is useful for parameters changed indirectly, e.g., using a custom SQL query or within a stored function.
	 *
	 * Use {@link enableCaching()} to enable the caching again.
	 *
	 * @param string|string[] $paramName one or more names of parameters to disabled caching values of;
	 *                                   <tt>null</tt> disables the caching altogether
	 */
	function disableCaching($paramName = null);

	/**
	 * Enables caching of configuration parameter values again, after it has been disabled by {@link disableCaching()}.
	 *
	 * Note that, if caching was disabled altogether using {@link disableCaching()}, this method will only turn caching
	 * on for the requested parameters. Others will remain non-cached. To turn caching generally on, use `null` as the
	 * argument (which is the default).
	 *
	 * @param string|string[] $paramName
	 */
	function enableCaching($paramName = null);
}

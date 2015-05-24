<?php
namespace Ivory;

class Ivory
{
	/** @var IConnection[] map: name => connection */
	private static $connections = [];
	/** @var IConnection */
	private static $defaultConn = null;


	/**
	 * Sets up a new database connection.
	 *
	 * If this is the first connection to set up, it is automatically set as the default connection.
	 *
	 * The connection with the database is not immediately established - just a {@link Connection} object is initialized
	 * and returned, it connects to the database on demand.
	 *
	 * @param mixed $params the parameters for creating the connection;
	 *                      anything accepted by {@link ConnectionParameters::create()} - one of the following options:
	 *                        <ul>
	 *                          <li>a {@link ConnectionParameters} object
	 *                          <li>a URI, e.g., <tt>"postgresql://usr@localhost:5433/db?connect_timeout=10"</tt>
	 *                          <li>a PostgreSQL connection string, e.g.,
	 *                              <tt>"host=localhost port=5432 dbname=mydb connect_timeout=10"</tt>
	 *                          <li>a map of connection parameter keywords to values, e.g., <tt>['host' => '/tmp']</tt>
	 *                        </ul>
	 * @param string|null $connName name for the connection;
	 *                              if not given, the database name is considered if it is given within <tt>$params</tt>
	 *                                or the fallback name <tt>'conn'</tt> is used;
	 *                              if not given and if the auto-generated name is already taken, it is appended with a
	 *                                numeric prefix, e.g., <tt>'conn1'</tt>, <tt>'conn2'</tt>, etc.
	 * @return Connection
	 * @throws ConnectionException if connection name is explicitly specified but a connection with the same name
	 *                               already exists
	 */
	public static function setupConnection($params, $connName = null)
	{
		if (!$params instanceof ConnectionParameters) {
			$params = ConnectionParameters::create($params);
		}

		if ($connName === null) {
			$connName = (isset($params['dbname']) ? $params['dbname'] : 'conn');
			for ($i = 1; $i < 10000; $i++) {
				if (!isset(self::$connections[$connName . $i])) {
					$connName .= $i;
					break;
				}
			}
		}

		$conn = new Connection($connName, $params);

		if (empty(self::$connections)) {
			self::setConnectionAsDefault($conn);
		}

		self::$connections[$connName] = $conn;

		return $conn;
	}

	/**
	 * @param string|null $connName name of the connection to get; <tt>null</tt> to get the default connection
	 * @return IConnection
	 * @throws \RuntimeException if the default connection is requested but no connection has been setup yet, or if the
	 *                             requested connection is not defined
	 */
	public static function getConnection($connName = null)
	{
		if ($connName === null) {
			if (self::$defaultConn) {
				return self::$defaultConn;
			}
			else {
				throw new \RuntimeException('No connection has been setup');
			}
		}

		if (isset(self::$connections[$connName])) {
			return self::$connections[$connName];
		}
		else {
			throw new \RuntimeException('Undefined connection');
		}
	}

	/**
	 * @param IConnection|string $conn (name of) connection to set as the default connection
	 * @throws \RuntimeException if the requested connection is not defined
	 */
	public static function setConnectionAsDefault($conn)
	{
		if (is_string($conn)) {
			if (isset(self::$connections[$conn])) {
				$conn = self::$connections[$conn];
			}
			else {
				throw new \RuntimeException('Undefined connection');
			}
		}
		elseif (!$conn instanceof IConnection) {
			throw new \InvalidArgumentException('conn');
		}

		self::$defaultConn = $conn;
	}
}

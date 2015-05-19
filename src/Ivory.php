<?php
namespace Ivory;

class Ivory
{
	/** @var IConnection[] map: name => connection */
	private static $connections = [];
	/** @var string */
	private static $defaultConnName = null;


	/**
	 * @param mixed $params the parameters for creating the connection;
	 * @param string|null $connName name for the connection;
	 *                              if not given, it is auto-generated from the database name and written to $connName
	 * @return Connection
	 */
	public static function setupConnection($params, &$connName = null)
	{
		$conn = new Connection();

		if ($connName === null) {
			$connName = $conn->getDbName();
		}

		self::$connections[$connName] = $conn;

		return $conn;
	}

	/**
	 * @param string $connName
	 * @return IConnection
	 */
	public static function getConnection($connName)
	{
		if (isset(self::$connections[$connName])) {
			return self::$connections[$connName];
		}
		else {
			throw new \RuntimeException('Undefined connection');
		}
	}

	/**
	 * @param IConnection|string $conn (name of) connection to set as the default connection
	 */
	public static function setConnectionAsDefault($conn)
	{


		if (!isset(self::$connections[$connName])) {
			throw new \RuntimeException('Undefined connection');
		}

		self::$defaultConnName = $connName;
	}
}

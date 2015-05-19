<?php
namespace Ivory;

/**
 * Parameters of a database connection.
 */
class ConnectionParameters
{
	private $host;
	private $port;
	private $dbName;
	private $username;
	private $password;


	/**
	 * Creates a connection parameters object from a URL, e.g., <tt>"pgsql://postgres@localhost:5433/mydb"</tt>.
	 *
	 * The URL is processed as follows:
	 * <ul>
	 *   <li>the only supported scheme is "pgsql"
	 *   <li>username and password are used as credentials when connecting; the password may be omitted (no password)
	 *   <li>server and port specify the server and port to connect to; the port may be omitted (use default port)
	 *   <li>the path specifies the name of the database to connect to
	 * </ul>
	 *
	 * @param string $url
	 * @return ConnectionParameters
	 */
	public static function fromUrl($url)
	{
		$c = parse_url($url);
		if ($c === false) {
			throw new \InvalidArgumentException('url is malformed');
		}

		if (!isset($c[PHP_URL_SCHEME], $c[PHP_URL_HOST], $c[PHP_URL_USER], $c[PHP_URL_PATH])) {
			throw new \InvalidArgumentException('url is incomplete');
		}

		if ($c[PHP_URL_SCHEME] != 'pgsql') {
			throw new UnsupportedException('Only pgsql scheme is supported');
		}

		return new ConnectionParameters(
			$c[PHP_URL_HOST],
			(isset($c[PHP_URL_PORT]) ? $c[PHP_URL_PORT] : null),
			$c[PHP_URL_PATH],
			$c[PHP_URL_USER],
			(isset($c[PHP_URL_PASS]) ? $c[PHP_URL_PASS] : null)
		);
	}

	/**
	 * Creates a connection parameters object from an associative array of parameters.
	 *
	 * The following parameters are recognized within the given array:
	 * <ul>
	 *   <li><tt>host (string)</tt>: the database server to connect to
	 *   <li><tt>port (int)</tt>: the port to connect to; optional (if skipped, connect to the default port)
	 *   <li><tt>username (string)</tt>: database username
	 *   <li><tt>password (string)</tt>: password for the given username; optional (the user might have no password set)
	 *   <li><tt>database (string)</tt>: name of the database to connect to
	 * </ul>
	 *
	 * @param array $params
	 * @return ConnectionParameters
	 */
	public static function fromArray(array $params)
	{
		if (!isset($params['host'], $params['username'], $params['database'])) {
			throw new \InvalidArgumentException('params are incomplete');
		}

		return new ConnectionParameters(
			$params['host'],
			(isset($params['port']) ? $params['port'] : null),
			$params['database'],
			$params['username'],
			(isset($params['password']) ? $params['password'] : null)
		);
	}


	/**
	 * @param string $host
	 * @param int|null $port
	 * @param string $dbName
	 * @param string $username
	 * @param string|null $password
	 */
	public function __construct($host, $port, $dbName, $username, $password)
	{
		$this->host = $host;
		$this->port = $port;
		$this->dbName = $dbName;
		$this->username = $username;
		$this->password = $password;
	}

	/**
	 * @return string
	 */
	public function getHost()
	{
		return $this->host;
	}

	/**
	 * @return int|null
	 */
	public function getPort()
	{
		return $this->port;
	}

	/**
	 * @return string
	 */
	public function getDbName()
	{
		return $this->dbName;
	}

	/**
	 * @return string
	 */
	public function getUsername()
	{
		return $this->username;
	}

	/**
	 * @return string|null
	 */
	public function getPassword()
	{
		return $this->password;
	}
}
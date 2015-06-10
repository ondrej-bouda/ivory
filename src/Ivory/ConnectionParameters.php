<?php
namespace Ivory;

/**
 * Parameters of a database connection.
 */
class ConnectionParameters implements \ArrayAccess, \IteratorAggregate
{
	private $params = [];


	public static function create($params)
	{
		if (is_array($params)) {
			return new ConnectionParameters($params);
		}
		elseif (is_string($params)) {
			if (preg_match("~^[^=']+://~", $params)) {
				return self::fromUri($params);
			}
			else {
				return self::fromConnectionString($params);
			}
		}
		elseif ($params instanceof ConnectionParameters) {
			return clone $params;
		}
		else {
			throw new \InvalidArgumentException('params');
		}
	}


	/**
	 * Creates a connection parameters object from a RFC 3986 URI, e.g., <tt>"postgresql://usr@localhost:5433/db"</tt>.
	 *
	 * The accepted URI is the same as for the libpq connect function, described in the
	 * <a href="http://www.postgresql.org/docs/9.4/static/libpq-connect.html">PostgreSQL documentation</a>.
	 *
	 * The following holds for the accepted URIs:
	 * <ul>
	 *   <li>the URI scheme designator must either be <tt>"postgresql"</tt> or <tt>"postgres"</tt>,
	 *   <li>any part of the URI is optional,
	 *   <li>username and password are used as credentials when connecting,
	 *   <li>server and port specify the server and port to connect to,
	 *   <li>the path specifies the name of the database to connect to,
	 *   <li>URI parameters are also supported, e.g., <tt>"postgresql:///mydb?host=localhost&port=5433"</tt>.
	 * </ul>
	 *
	 * @param string $uri
	 * @return ConnectionParameters
	 */
	public static function fromUri($uri)
	{
		$c = parse_url($uri);
		if ($c === false) {
			// NOTE: parse_url() denies the input if the host part is omitted, even though RFC 3986 says it is optional
			$auxUri = preg_replace('~//~', '//host', $uri, 1, $found); // NOTE: only preg_replace has a limit :-(
			if (!$found || ($c = parse_url($auxUri)) === false) {
				throw new \InvalidArgumentException('uri is malformed');
			}
			unset($c['host']);
		}
		if (!isset($c['scheme'])) {
			throw new \InvalidArgumentException('uri scheme not specified');
		}
		if ($c['scheme'] != 'postgresql' && $c['scheme'] != 'postgres') {
			throw new UnsupportedException('Only "postgresql" or "postgres" scheme is supported');
		}

		$params = array_filter(
			[
				'host' => (isset($c['host']) ? $c['host'] : null),
				'port' => (isset($c['port']) ? $c['port'] : null),
				'dbname' => (isset($c['path']) && strlen($c['path']) > 1 ? substr($c['path'], 1) : null),
				'user' => (isset($c['user']) ? $c['user'] : null),
				'password' => (isset($c['pass']) ? $c['pass'] : null),
			],
			'strlen'
		);
		if (isset($c['query'])) {
			parse_str($c['query'], $pars);
			$params = array_merge($params, $pars);
		}

		foreach ($params as &$par) {
			$par = rawurldecode($par); // NOTE: neither parse_url() nor parse_str() do that automatically
		}

		return new ConnectionParameters($params);
	}

	/**
	 * Creates a connection parameters object from a PostgreSQL connection string (see {@link pg_connect()}).
	 *
	 * A connection string is a set of <tt>keyword = value</tt> pairs, separated by space. Spaces around the equal sign
	 * are optional. To contain special characters, the value may be enclosed in single quotes, using backslash as the
	 * escape character.
	 *
	 * For details about the connection parameter keywords and values, see {@link __construct()}.
	 *
	 * @param string $connStr a PostgreSQL connection string
	 * @return ConnectionParameters
	 */
	public static function fromConnectionString($connStr)
	{
		$params = [];
		$offset = 0;
		while (preg_match("~\\s*([^=\\s]+)\\s*=\\s*([^'\\s]+|'(?:[^'\\\\]|\\\\['\\\\])*')~", $connStr, $m, 0, $offset)) {
			$k = $m[1];
			$v = $m[2];
			if ($v[0] == "'") {
				$v = strtr(substr($v, 1, -1), ["\\'" => "'", '\\\\' => '\\']);
			}
			$params[$k] = $v;
			$offset += strlen($m[0]);
		}
		if (strlen(trim(substr($connStr, $offset))) > 0) {
			throw new \InvalidArgumentException('connStr');
		}

		return new ConnectionParameters($params);
	}

	/**
	 * Initializes the connection parameters from an associative array of keywords to values.
	 *
	 * The most important are the following parameters:
	 * <ul>
	 *   <li><tt>host (string)</tt>: the database server to connect to,
	 *   <li><tt>port (int)</tt>: the port to connect to,
	 *   <li><tt>username (string)</tt>: username to authenticate as,
	 *   <li><tt>password (string)</tt>: password for the given username,
	 *   <li><tt>database (string)</tt>: name of the database to connect to,
	 *   <li><tt>connect_timeout (int></tt>: connection timeout (0 means to wait indefinitely),
	 *   <li><tt>options (string)</tt>: the runtime options to send to the server.
	 * </ul>
	 * For details, see the PostgreSQL documentation (the link below).
	 * Any parameter may be omitted - the default is used then.
	 *
	 * @param array $params map: connection parameter keyword => value
	 * @link http://www.postgresql.org/docs/current/static/libpq-connect.html#LIBPQ-PARAMKEYWORDS
	 */
	public function __construct($params)
	{
		$this->params = $params;
	}

	/**
	 * @return string connection string suitable for the pg_connect() function
	 */
	public function buildConnectionString()
	{
		$kvPairs = [];
		foreach ($this->params as $k => $v) {
			if (strlen($v) == 0 || preg_match("~[\\s']~", $v)) {
				$vstr = "'" . strtr($v, ["'" => "\\'", '\\' => '\\\\']) . "'";
			}
			else {
				$vstr = $v;
			}

			$kvPairs[] = $k . '=' . $vstr;
		}

		return implode(' ', $kvPairs);
	}


	/**
	 * @return string|null
	 */
	public function getHost()
	{
		return (isset($this->params['host']) ? $this->params['host'] : null);
	}

	/**
	 * @return int|null
	 */
	public function getPort()
	{
		return (isset($this->params['port']) ? (int)$this->params['port'] : null);
	}

	/**
	 * @return string|null
	 */
	public function getDbName()
	{
		return (isset($this->params['dbname']) ? $this->params['dbname'] : null);
	}

	/**
	 * @return string|null
	 */
	public function getUsername()
	{
		return (isset($this->params['user']) ? $this->params['user'] : null);
	}

	/**
	 * @return string|null
	 */
	public function getPassword()
	{
		return (isset($this->params['password']) ? $this->params['password'] : null);
	}


	public function getIterator()
	{
		return new \ArrayIterator($this->params);
	}


	public function offsetExists($offset)
	{
		return array_key_exists($offset, $this->params);
	}

	public function offsetGet($offset)
	{
		return $this->params[$offset];
	}

	public function offsetSet($offset, $value)
	{
		$this->params[$offset] = ($value === null ? null : (string)$value);
	}

	public function offsetUnset($offset)
	{
		unset($this->params[$offset]);
	}
}
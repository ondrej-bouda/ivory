<?php
namespace Ivory;

class Connection implements IConnection
{
	private $name;
	private $params;

	/** @var resource the connection handler */
	private $handler = null;


	/**
	 * @param string $name name for the connection
	 * @param ConnectionParameters|array|string $params either a connection parameters object, or an associative array
	 *                                                    of parameters for {@link ConnectionParameters::__construct()},
	 *                                                    or a URL for {@link ConnectionParameters::fromUrl()},
	 *                                                    or a PostgreSQL connection string (see {@link pg_connect()})
	 */
	public function __construct($name, $params)
	{
		$this->name = $name;
		$this->params = ConnectionParameters::create($params);
	}

	final public function getName()
	{
		return $this->name;
	}

	public function getConnectionParameters()
	{
		return $this->params;
	}

	public function isConnected()
	{
		if ($this->handler === null) {
			return null;
		}
		else {
			// NOTE: pg_connection_status() may also return null
			return (pg_connection_status($this->handler) === PGSQL_CONNECTION_OK);
		}
	}

	public function connect()
	{
		if ($this->handler !== null) {
			return false;
		}

		$this->handler = pg_connect($this->params->buildConnectionString(), PGSQL_CONNECT_FORCE_NEW);
		if ($this->handler === false) {
			throw new ConnectionException('Error connecting to the database');
		}

		return true;
	}

	public function close()
	{
		if ($this->handler === null) {
			return false;
		}

		// TODO: handle the case when there are any open LO handles (an LO shall be an IType registered at the connection)

		$closed = pg_close($this->handler);
		if (!$closed) {
			throw new ConnectionException('Error closing the connection');
		}
		$this->handler = null;
		return true;
	}
}

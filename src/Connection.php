<?php
namespace Ivory;

class Connection implements IConnection
{
	private $params;

	/** @var resource the connection handler */
	private $handler = null;


	/**
	 * @param ConnectionParameters|array|string $params either a connection parameters object, or an associative array
	 *                                                    of parameters for {@link ConnectionParameters::fromArray()},
	 *                                                    or a URL for {@link ConnectionParameters::fromUrl()}
	 */
	public function __construct($params)
	{
		if (is_array($params)) {
			$params = ConnectionParameters::fromArray($params);
		}
		elseif (is_string($params)) {
			$params = ConnectionParameters::fromUrl($params);
		}
		elseif (!$params instanceof ConnectionParameters) {
			throw new \InvalidArgumentException('params');
		}

		$this->params = $params;
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
			return (pg_connection_status($this->handler) == PGSQL_CONNECTION_OK);
		}
	}

	public function close()
	{
		if ($this->handler !== null) {
			// TODO: handle the case if there are any open LO handles
			$closed = pg_close($this->handler);
			if (!$closed) {
				throw new
			}
			$this->handler = null;
			return true;
		}
		else {
			return false;
		}
	}
}

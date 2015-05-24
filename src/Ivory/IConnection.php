<?php
namespace Ivory;

interface IConnection
{
	/**
	 * @return string name of the connection
	 */
	function getName();

	/**
	 * @return ConnectionParameters
	 */
	function getConnectionParameters();

	/**
	 * @return bool|null <tt>true</tt> if the connection is working,
	 *                   <tt>null</tt> if no connection is established (yet),
	 *                   <tt>false</tt> if the connection is established, but broken
	 */
	function isConnected();

	/**
	 * Establishes a connection with the database according to the current connection parameters, if it has not been
	 * established yet for this connection object.
	 *
	 * The connection is not shared with any other <tt>IConnection</tt> objects.
	 *
	 * TODO: on PHP 5.6.0+, connect asynchronously, then poll upon the first command actually requiring the connection; introduce a connectWait() variant which connects synchronously
	 *
	 * @return bool <tt>true</tt> if the connection has actually been established,
	 *              <tt>false</tt> if the connection has already been open and thus this was a no-op
	 * @throws ConnectionException on error connecting to the database
	 */
	function connect();

	/**
	 * Closes the connection (if any).
	 *
	 * @return bool <tt>true</tt> if the connection has actually been closed,
	 *              <tt>false</tt> if no connection was established and thus this was a no-op
	 * @throws ConnectionException on error closing the connection
	 */
	function close();
}

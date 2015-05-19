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
	 * Closes the connection (if any).
	 *
	 * @return bool <tt>true</tt> if the connection has actually been closed,
	 *              <tt>false</tt> if no connection was established and thus this was a no-op
	 */
	function close();
}

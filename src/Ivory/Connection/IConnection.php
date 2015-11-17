<?php
namespace Ivory\Connection;

/**
 * Connection to a database.
 *
 * Note the default mode of connecting to the database, using {@link IConnection::connect()}, is asynchronous. It is
 * thus recommended to start connecting as soon as the connection parameters are known. If synchronous connection is
 * needed, use {@link IConnection::connectWait()} instead of {@link IConnection::connect()}.
 *
 * Note there is no option for using a persistent connection on the PHP side (i.e., those done be {@link pg_pconnect()})
 * as this feature is known to be neither 100% correct nor especially effective. Use server-side connection pooling
 * instead.
 *
 * Apart from actually arranging the communication between the user code and a PostgreSQL database, the interface also
 * provides means of controlling various aspects of the session itself, especially the transaction control. These might
 * of course be also managed by custom SQL queries sent to the database; circumventing the designated methods of this
 * interface should be avoided, though, because the `IConnection` object tracks the state of the connection and would
 * end up in an undefined state if it was not aware of some control queries.
 */
interface IConnection
    extends IConnectionControl, ITypeControl, ISessionControl, ITransactionControl, IIPCControl, IStatementExecution,
            ICopyControl
{
    /**
     * @return string name of the connection
     */
    function getName();
}

// TODO: do not extend the whole interfaces - some methods shall are unnecessary to the public, e.g., getTypeDictionary()

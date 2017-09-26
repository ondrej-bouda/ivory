<?php
namespace Ivory\Connection;

interface IIPCControl
{
    /**
     * Returns ID of the database server process. This might be useful for comparison with PID of a notifying process.
     *
     * @return int
     */
    function getBackendPID(): int;

    /**
     * Notifies all listeners of a given channel.
     *
     * See the {@see http://www.postgresql.org/docs/9.4/static/sql-notify.html PostgreSQL documentation} for details
     * regarding the asynchronous notifications, especially the behaviour regarding transactions.
     *
     * @param string $channel name of channel to send notification to
     * @param string|null $payload optional payload to send along with the notification;
     *                             note the maximal accepted length depends on the database configuration
     */
    function notify(string $channel, ?string $payload = null): void;

    /**
     * Starts listening to the specified channel.
     *
     * Calling this method merely enables the current session to receive notifications through the given channel. To
     * actually get some, use {@link pollNotification()}.
     *
     * @param string $channel name of channel to listen to
     */
    function listen(string $channel): void;

    /**
     * Stops listening to the specified channel.
     *
     * Use {@link unlistenAll()} to stop listening from all channels currently listening to.
     *
     * @param string $channel name of channel to stop listening to
     */
    function unlisten(string $channel): void;

    /**
     * Stops listening to any channel.
     *
     * Use {@link unlisten()} to stop listening only to a specified channel.
     */
    function unlistenAll(): void;

    /**
     * Polls the queue of waiting IPC notifications on channels being listened to.
     *
     * @return Notification|null the next notification in the queue, or <tt>null</tt> if there is no notification there
     */
    function pollNotification(): ?Notification;
}

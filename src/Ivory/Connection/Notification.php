<?php
namespace Ivory\Connection;

/**
 * Notification received from an IPC channel.
 *
 * The objects are immutable.
 */
class Notification
{
    private $channel;
    private $pid;
    private $payload;

    public function __construct($channel, $pid, $payload)
    {
        $this->channel = $channel;
        $this->pid = $pid;
        $this->payload = (string)$payload;
    }

    /**
     * @return string name of the IPC channel the notification was sent through
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @return int ID of the database server process which sent the notification
     */
    public function getPid()
    {
        return $this->pid;
    }

    /**
     * @return string the notification payload
     */
    public function getPayload()
    {
        return $this->payload;
    }
}

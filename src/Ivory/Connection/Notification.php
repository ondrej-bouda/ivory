<?php
declare(strict_types=1);
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

    public function __construct(string $channel, int $pid, ?string $payload)
    {
        $this->channel = $channel;
        $this->pid = $pid;
        $this->payload = $payload;
    }

    /**
     * @return string name of the IPC channel the notification was sent through
     */
    public function getChannel(): string
    {
        return $this->channel;
    }

    /**
     * @return int ID of the database server process which sent the notification
     */
    public function getSenderBackendPID(): int
    {
        return $this->pid;
    }

    /**
     * @return string|null the notification payload, or <tt>null</tt> if no payload is contained
     */
    public function getPayload(): ?string
    {
        return $this->payload;
    }
}

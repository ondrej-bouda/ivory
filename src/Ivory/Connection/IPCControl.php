<?php
declare(strict_types=1);

namespace Ivory\Connection;

class IPCControl implements IIPCControl
{
    private $connCtl;


    public function __construct(ConnectionControl $connCtl)
    {
        $this->connCtl = $connCtl;
    }


    public function getBackendPID(): int
    {
        return pg_get_pid($this->connCtl->requireConnection());
    }

    public function notify(string $channel, ?string $payload = null): void
    {
        // TODO: Implement notify() method.
    }

    public function listen(string $channel): void
    {
        // TODO: Implement listen() method.
    }

    public function unlisten(string $channel): void
    {
        // TODO: Implement unlisten() method.
    }

    public function unlistenAll(): void
    {
        // TODO: Implement unlistenAll() method.
    }

    public function pollNotification(): ?Notification
    {
        $handler = $this->connCtl->requireConnection();

        $res = pg_get_notify($handler, PGSQL_ASSOC);
        if ($res === false) {
            return null;
        }
        return new Notification($res['message'], $res['pid'], (string)$res['payload']);
    }
}

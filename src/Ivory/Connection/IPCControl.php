<?php
declare(strict_types=1);

namespace Ivory\Connection;

class IPCControl implements IIPCControl
{
    private $connCtl;
    private $stmtExec;


    public function __construct(ConnectionControl $connCtl, IStatementExecution $stmtExec)
    {
        $this->connCtl = $connCtl;
        $this->stmtExec = $stmtExec;
    }


    public function getBackendPID(): int
    {
        $handler = $this->connCtl->requireConnection();
        return pg_get_pid($handler);
    }

    public function notify(string $channel, ?string $payload = null): void
    {
        if ($payload === null) {
            $this->stmtExec->command('NOTIFY %ident', $channel);
        } else {
            $this->stmtExec->command('NOTIFY %ident, %s', $channel, $payload);
        }
    }

    public function listen(string $channel): void
    {
        $this->stmtExec->command('LISTEN %ident', $channel);
    }

    public function unlisten(string $channel): void
    {
        $this->stmtExec->command('UNLISTEN %ident', $channel);
    }

    public function unlistenAll(): void
    {
        $this->stmtExec->rawCommand('UNLISTEN *');
    }

    public function pollNotification(): ?Notification
    {
        $handler = $this->connCtl->requireConnection();
        $res = pg_get_notify($handler, PGSQL_ASSOC);
        if ($res === false) {
            return null;
        }
        $payload = ($res['payload'] !== '' ? $res['payload'] : null);
        return new Notification($res['message'], $res['pid'], $payload);
    }
}

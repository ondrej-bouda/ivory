<?php
namespace Ivory\Connection;

class IPCControl implements IIPCControl
{
    private $connCtl;


    public function __construct(ConnectionControl $connCtl)
    {
        $this->connCtl = $connCtl;
    }


    public function getBackendPID()
    {
        return pg_get_pid($this->connCtl->requireConnection());
    }

    public function notify($channel, $payload = null)
    {
        // TODO: Implement notify() method.
    }

    public function listen($channel)
    {
        // TODO: Implement listen() method.
    }

    public function unlisten($channel)
    {
        // TODO: Implement unlisten() method.
    }

    public function unlistenAll()
    {
        // TODO: Implement unlistenAll() method.
    }

    public function pollNotification()
    {
        $handler = $this->connCtl->requireConnection();

        $res = pg_get_notify($handler, PGSQL_ASSOC);
        if ($res === false) {
            return null;
        }
        return new Notification($res['message'], $res['pid'], $res['payload']);
    }
}

<?php
namespace Ivory\Connection;

use Ivory\Connection\Config\IConnConfig;

interface ISessionControl
{
    /**
     * @return IConnConfig runtime configuration of the connection
     */
    function getConfig(): IConnConfig;
}

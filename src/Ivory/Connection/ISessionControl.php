<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Connection\Config\IConnConfig;

interface ISessionControl
{
    /**
     * @return IConnConfig runtime configuration of the connection
     */
    function getConfig(): IConnConfig;
}

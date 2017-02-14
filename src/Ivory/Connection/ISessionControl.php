<?php
namespace Ivory\Connection;

interface ISessionControl
{
    /**
     * @return IConnConfig runtime configuration of the connection
     */
    function getConfig(): IConnConfig;
}

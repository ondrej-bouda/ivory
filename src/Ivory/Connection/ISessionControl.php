<?php
namespace Ivory\Connection;

interface ISessionControl
{
    /**
     * @return ConnConfig runtime configuration of the connection
     */
    function getConfig();
}

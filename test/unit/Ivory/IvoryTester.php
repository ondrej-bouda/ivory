<?php
namespace Ivory;

class IvoryTester extends \PHPUnit_Extensions_Database_DefaultTester
{
    protected function getSetUpOperation()
    {
        return \PHPUnit_Extensions_Database_Operation_Factory::CLEAN_INSERT(true); // cascading is necessary for truncating tables referred to by foreign keys
    }
}

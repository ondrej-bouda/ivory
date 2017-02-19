<?php
namespace Ivory;

class IvoryTester extends \PHPUnit\DbUnit\DefaultTester
{
    protected function getSetUpOperation()
    {
        return \PHPUnit\DbUnit\Operation\Factory::CLEAN_INSERT(true); // cascading is necessary for truncating tables referred to by foreign keys
    }
}

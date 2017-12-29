<?php
declare(strict_types=1);
namespace Ivory;

use PHPUnit\DbUnit;

class IvoryTester extends DbUnit\DefaultTester
{
    protected function getSetUpOperation()
    {
        return DbUnit\Operation\Factory::CLEAN_INSERT(true); // cascading is necessary for truncating tables referred to by foreign keys
    }
}

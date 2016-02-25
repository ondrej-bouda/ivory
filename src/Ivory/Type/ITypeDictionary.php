<?php
namespace Ivory\Type;

use Ivory\Exception\UndefinedTypeException;

interface ITypeDictionary
{
    /**
     * @param int $oid
     * @return IType
     * @throws UndefinedTypeException
     */
    function requireTypeByOid($oid);
}

<?php
namespace Ivory\Type;

use Ivory\Exception\UndefinedTypeException;

class TypeDictionary implements ITypeDictionary
{
    /** @var IType[] */
    private $typeMap = [];
    /** @var \Closure|null */
    private $undefinedTypeHandler = null;

    public function defineType($oid, IType $type)
    {
        $this->typeMap[$oid] = $type;
    }

    /**
     * @return \Closure|null
     */
    public function getUndefinedTypeHandler()
    {
        return $this->undefinedTypeHandler;
    }

    /**
     * @param \Closure|null $undefinedTypeHandler
     */
    public function setUndefinedTypeHandler($undefinedTypeHandler)
    {
        $this->undefinedTypeHandler = $undefinedTypeHandler;
    }

    public function requireTypeByOid($oid)
    {
        if (isset($this->typeMap[$oid])) {
            return $this->typeMap[$oid];
        }

        if ($this->undefinedTypeHandler !== null) {
            $type = call_user_func($this->undefinedTypeHandler, $oid);
            if ($type !== null) {
                return $type;
            }
        }

        throw new UndefinedTypeException("There is no type defined for OID $oid");
    }

    /**
     * Exports the current state of the dictionary to a PHP class implementing ITypeDictionary.
     */
    public function export()
    {
        // TODO
    }
}

<?php
namespace Ivory\Connection;

use Ivory\Exception\UndefinedTypeException;
use Ivory\Ivory;
use Ivory\Type\ITypeProvider;
use Ivory\Type\TypeDictionary;
use Ivory\Type\IntrospectingTypeDictionaryCompiler;
use Ivory\Type\TypeRegister;

class TypeControl implements ITypeControl, ITypeProvider
{
    private $connection;
    private $typeRegister;
    /** @var TypeDictionary|null */
    private $typeDictionary = null;

    public function __construct(IConnection $connection)
    {
        $this->connection = $connection;
        $this->typeRegister = new TypeRegister();
    }


    //region ITypeControl

    public function getTypeRegister()
    {
        return $this->typeRegister;
    }

    public function getTypeDictionary()
    {
        if ($this->typeDictionary === null) {
            $this->typeDictionary = new TypeDictionary(); // FIXME: instead of empty dictionary, use a cached dictionary; for getting a cached dictionary, use TypeDictionary::export()
            $this->initUndefinedTypeHandler();
        }
        return $this->typeDictionary;
    }

    private function initUndefinedTypeHandler()
    {
        $this->typeDictionary->setUndefinedTypeHandler(function ($oid) {
            $compiler = new IntrospectingTypeDictionaryCompiler();
            $dict = $compiler->compileTypeDictionary($this);
            $type = $dict->requireTypeFromOid($oid);
            $this->typeDictionary = $dict; // replacing the current dictionary only when the requested type is found in the new dictionary
            $this->initUndefinedTypeHandler();
            return $type;
        });
    }

    public function flushTypeDictionary()
    {
        $this->typeDictionary = null;
    }

    //endregion

    //region ITypeProvider

    public function requireBaseType($schemaName, $typeName)
    {
        $localReg = $this->getTypeRegister();
        $globalReg = Ivory::getTypeRegister();
        foreach ([$localReg, $globalReg] as $reg) {
            /** @var TypeRegister $reg */
            $type = $reg->getType($schemaName, $typeName);
            if ($type !== null) {
                return $type;
            }
            $type = $reg->loadType($schemaName, $typeName, $this->connection);
            if ($type !== null) {
                return $type;
            }
        }
        throw new UndefinedTypeException("$schemaName.$typeName on connection {$this->connection->getName()}");
    }

    //endregion
}

<?php
namespace Ivory\Connection;

use Ivory\Ivory;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\ITypeProvider;
use Ivory\Type\TypeDictionary;
use Ivory\Type\IntrospectingTypeDictionaryCompiler;
use Ivory\Type\TypeRegister;

class TypeControl implements ITypeControl, ITypeProvider
{
    private $connection;
    private $connCtl;
    private $typeRegister;
    /** @var TypeDictionary|null */
    private $typeDictionary = null;

    public function __construct(IConnection $connection, ConnectionControl $connCtl)
    {
        $this->connection = $connection;
        $this->connCtl = $connCtl;
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
            $compiler = new IntrospectingTypeDictionaryCompiler($this->connection, $this->connCtl->requireConnection());
            $dict = $compiler->compileTypeDictionary($this);
            $type = $dict->requireTypeByOid($oid);
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

    public function provideType($schemaName, $typeName)
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
        return null;
    }

    public function provideRangeCanonicalFunc($schemaName, $funcName, ITotallyOrderedType $subtype)
    {
        $localReg = $this->getTypeRegister();
        $globalReg = Ivory::getTypeRegister();
        foreach ([$localReg, $globalReg] as $reg) {
            /** @var TypeRegister $reg */
            $func = $reg->getRangeCanonicalFunc($schemaName, $funcName, $subtype);
            if ($func !== null) {
                return $func;
            }
            $func = $reg->provideRangeCanonicalFunc($schemaName, $funcName, $subtype);
            if ($func !== null) {
                return $func;
            }
        }
        return null;
    }

    //endregion
}

<?php
namespace Ivory\Connection;

use Ivory\Ivory;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\ITypeDictionary;
use Ivory\Type\ITypeDictionaryUndefinedHandler;
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

    public function getTypeRegister(): TypeRegister
    {
        return $this->typeRegister;
    }

    public function getTypeDictionary(): ITypeDictionary
    {
        if ($this->typeDictionary === null) {
            $this->typeDictionary = new TypeDictionary(); // TODO: instead of empty dictionary, use a cached dictionary; for getting a cached dictionary, use TypeDictionary::export()
            $this->initTypeDictionary($this->typeDictionary);
            $this->setupTypeDictionaryUndefinedHandler();
        }
        return $this->typeDictionary;
    }

    private function initTypeDictionary(TypeDictionary $typeDictionary)
    {
        $sp = $this->connection->getConfig()->getEffectiveSearchPath();
        $typeDictionary->setTypeSearchPath($sp);

        $globalReg = Ivory::getTypeRegister();
        $localReg = $this->getTypeRegister();
        foreach ([$globalReg, $localReg] as $reg) {
            /** @var TypeRegister $reg */
            foreach ($reg->getSqlPatternTypes() as $name => $type) {
                $typeDictionary->defineCustomType($name, $type);
            }
            foreach ($reg->getTypeAbbreviations() as $abbr => list($schemaName, $typeName)) {
                $typeDictionary->defineTypeAlias($abbr, $schemaName, $typeName);
                $typeDictionary->defineTypeAlias("{$abbr}[]", $schemaName, "{$typeName}[]");
            }
            $typeDictionary->addTypeRecognitionRuleSet($reg->getTypeRecognitionRules());
        }
    }

    private function setupTypeDictionaryUndefinedHandler()
    {
        $handler = function ($oid, $schemaName, $typeName, $value) {
            $compiler = new IntrospectingTypeDictionaryCompiler($this->connection, $this->connCtl->requireConnection());
            $dict = $compiler->compileTypeDictionary($this);
            $this->initTypeDictionary($dict);

            if ($oid !== null) {
                $type = $dict->requireTypeByOid($oid);
            } elseif ($typeName !== null) {
                $type = $dict->requireTypeByName($typeName, $schemaName);
            } elseif ($value !== null) {
                $type = $dict->requireTypeByValue($value);
            } else {
                return null;
            }

            // now that the requested type was really found, replace the current dictionary with the new one, which recognized the type
            $this->typeDictionary = $dict;
            $this->setupTypeDictionaryUndefinedHandler();

            return $type;
        };

        $this->typeDictionary->setUndefinedTypeHandler(new class($handler) implements ITypeDictionaryUndefinedHandler {
            private $handler;

            public function __construct(\Closure $handler)
            {
                $this->handler = $handler;
            }

            public function handleUndefinedType($oid, $schemaName, $typeName, $value)
            {
                return call_user_func($this->handler, $oid, $schemaName, $typeName, $value);
            }
        });
    }

    public function flushTypeDictionary()
    {
        $this->typeDictionary = null;
    }

    //endregion

    //region ITypeProvider

    public function provideType(string $schemaName, string $typeName)
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

    public function provideRangeCanonicalFunc(string $schemaName, string $funcName, ITotallyOrderedType $subtype)
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

<?php
namespace Ivory\Connection;

use Ivory\Ivory;
use Ivory\Type\ITypeDictionary;
use Ivory\Type\ITypeDictionaryUndefinedHandler;
use Ivory\Type\IntrospectingTypeDictionaryCompiler;
use Ivory\Type\TypeProviderList;
use Ivory\Type\TypeRegister;

class TypeControl implements ITypeControl
{
    private $connection;
    private $connCtl;
    private $typeRegister;
    /** @var ITypeDictionary|null */
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
            $this->typeDictionary = $this->connection->getCached(ITypeControl::TYPE_DICTIONARY_CACHE_KEY);
            if (!$this->typeDictionary instanceof ITypeDictionary) {
                $this->typeDictionary = $this->introspectTypeDictionary();
                $this->cacheTypeDictionary($this->typeDictionary);
            }

            $this->typeDictionary->attachToConnection($this->connection);
            $this->setupTypeDictionarySearchPath($this->typeDictionary);
            $this->setupTypeDictionaryUndefinedHandler();
        }

        return $this->typeDictionary;
    }

    private function introspectTypeDictionary(): ITypeDictionary
    {
        $compiler = new IntrospectingTypeDictionaryCompiler($this->connCtl->requireConnection());

        $typeProvider = new TypeProviderList();
        $typeProvider->appendTypeProvider($this->getTypeRegister());
        $typeProvider->appendTypeProvider(Ivory::getTypeRegister());

        $dict = $compiler->compileTypeDictionary($typeProvider);

        return $dict;
    }

    /**
     * Caches the type dictionary, if cache is enabled at the connection.
     *
     * @param ITypeDictionary $dict the dictionary to cache; must *not* be attached to the connection
     */
    private function cacheTypeDictionary(ITypeDictionary $dict)
    {
        if ($this->connection->isCacheEnabled()) {
            $this->typeDictionary->detachFromConnection();
            $this->connection->cachePermanently(ITypeControl::TYPE_DICTIONARY_CACHE_KEY, $dict);
        }
    }

    private function setupTypeDictionarySearchPath(ITypeDictionary $typeDictionary)
    {
        $sp = $this->connection->getConfig()->getEffectiveSearchPath();
        $typeDictionary->setTypeSearchPath($sp);
    }

    private function setupTypeDictionaryUndefinedHandler()
    {
        $handler = function ($oid, $schemaName, $typeName, $value) {
            $dict = $this->introspectTypeDictionary();
            $this->setupTypeDictionarySearchPath($dict);

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
            $this->cacheTypeDictionary($dict);
            $this->setupTypeDictionaryUndefinedHandler();

            $dict->attachToConnection($this->connection);

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
}

<?php
namespace Ivory\Connection;

use Ivory\Ivory;
use Ivory\Type\ITypeDictionary;
use Ivory\Type\ITypeDictionaryUndefinedHandler;
use Ivory\Type\IntrospectingTypeDictionaryCompiler;
use Ivory\Type\TrackingTypeDictionary;
use Ivory\Type\TypeDictionary;
use Ivory\Type\TypeProviderList;
use Ivory\Type\TypeRegister;

class TypeControl implements ITypeControl
{
    private $connection;
    private $connCtl;
    private $typeRegister;
    /** @var ITypeDictionary|null */
    private $typeDictionary = null;
    /** @var bool flag telling whether the current type dictionary has been loaded from the cache */
    private $typeDictionaryLoadedFromCache = false;

    public function __construct(IConnection $connection, ConnectionControl $connCtl)
    {
        $this->connection = $connection;
        $this->connCtl = $connCtl;
        $this->typeRegister = new TypeRegister();

        $this->connCtl->registerPostDisconnectHook(function () {
            $this->cacheTypeDictionary();
        });
    }

    public function cacheTypeDictionary()
    {
        if ($this->typeDictionary !== null && !$this->typeDictionaryLoadedFromCache &&
            $this->connection->isCacheEnabled())
        {
            $this->typeDictionary->detachFromConnection();
            if ($this->typeDictionary instanceof TrackingTypeDictionary) {
                $this->typeDictionary->disposeUnusedTypes();
            }
            $this->connection->cachePermanently(ITypeControl::TYPE_DICTIONARY_CACHE_KEY, $this->typeDictionary);
        }
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
            if ($this->typeDictionary instanceof ITypeDictionary) {
                $this->typeDictionaryLoadedFromCache = true;
                if ($this->typeDictionary instanceof TrackingTypeDictionary) {
                    // type usage watching is no more necessary - all types retrieved from cache are considered as being used
                    $this->typeDictionary->disableTypeUsageWatching();
                }
            } else {
                $this->typeDictionary = $this->introspectTypeDictionary();
                $this->typeDictionaryLoadedFromCache = false;
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

    private function setupTypeDictionarySearchPath(ITypeDictionary $typeDictionary)
    {
        $sp = $this->connection->getConfig()->getEffectiveSearchPath();
        $typeDictionary->setTypeSearchPath($sp);
    }

    private function setupTypeDictionaryUndefinedHandler()
    {
        $handler = function ($oid, $schemaName, $typeName, $value) {
            $newDict = $this->introspectTypeDictionary();
            $this->setupTypeDictionarySearchPath($newDict);

            if ($oid !== null) {
                $type = $newDict->requireTypeByOid($oid);
            } elseif ($typeName !== null) {
                $type = $newDict->requireTypeByName($typeName, $schemaName);
            } elseif ($value !== null) {
                $type = $newDict->requireTypeByValue($value);
            } else {
                return null;
            }

            // Now that the requested type was really found, replace the current dictionary with the new one, which
            // recognized the type.

            $oldDict = $this->typeDictionary;
            if ($newDict instanceof TrackingTypeDictionary && $oldDict instanceof TypeDictionary &&
                $this->typeDictionaryLoadedFromCache)
            {
                // Various requests are expected to use various sets of types. To prevent the dictionary to throw away
                // types which were unused now but were used before (and thus will probably be used further) and then
                // introspect them again, all the types loaded from cache are treated as being used.
                $newDict->markAllDictionaryTypesAsUsed($oldDict);
            }

            $this->typeDictionary = $newDict;
            $this->typeDictionaryLoadedFromCache = false;
            $this->setupTypeDictionaryUndefinedHandler();

            $newDict->attachToConnection($this->connection);

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

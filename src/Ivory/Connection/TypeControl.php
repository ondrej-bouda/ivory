<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Ivory;
use Ivory\Type\IType;
use Ivory\Type\ITypeDictionary;
use Ivory\Type\ITypeDictionaryUndefinedHandler;
use Ivory\Type\IntrospectingTypeDictionaryCompiler;
use Ivory\Type\TrackingTypeDictionary;
use Ivory\Type\TypeDictionary;
use Ivory\Type\TypeProviderList;
use Ivory\Type\TypeRegister;
use Psr\Cache\CacheException;

class TypeControl implements ITypeControl
{
    const OPTION_INTROSPECT_PLAIN_ARRAYS = 1;

    private $connection;
    private $connCtl;
    /** @var TypeRegister|null */
    private $typeRegister = null;
    /** @var ITypeDictionary|null */
    private $typeDictionary = null;
    /** @var ITypeDictionary|null */
    private $preloadedTypeDictionary = null;
    /**
     * @var bool|null flag telling whether the current type dictionary has been loaded from the cache;
     *                <tt>null</tt> if not yet attempted to load from cache
     */
    private $typeDictionaryLoadedFromCache = null;
    /** @var int */
    private $options = 0;

    public function __construct(IConnection $connection, ConnectionControl $connCtl)
    {
        $this->connection = $connection;
        $this->connCtl = $connCtl;

        $this->connCtl->registerConnectStartHook(function () {
            if ($this->typeDictionary === null) {
                $this->preloadedTypeDictionary = $this->loadTypeDictionaryFromCache();
            }
        });
        $this->connCtl->registerPostDisconnectHook(function () {
            $this->cacheTypeDictionary();
        });
    }

    private function cacheTypeDictionary(): void
    {
        if ($this->typeDictionary !== null && $this->typeDictionaryLoadedFromCache !== true &&
            $this->connection->isCacheEnabled())
        {
            $this->typeDictionary->detachFromConnection();
            if ($this->typeDictionary instanceof TrackingTypeDictionary) {
                $this->typeDictionary->disposeUnusedTypes();
            }
            try {
                $this->connection->cachePermanently(ITypeControl::TYPE_DICTIONARY_CACHE_KEY, $this->typeDictionary);
            } catch (CacheException $e) {
                trigger_error(
                    'Error caching the type dictionary, the performance will be degraded. Exception message: ' .
                    $e->getMessage(),
                    E_USER_WARNING
                );
            }
        }
    }

    private function loadTypeDictionaryFromCache(): ?ITypeDictionary
    {
        try {
            $dict = $this->connection->getCached(ITypeControl::TYPE_DICTIONARY_CACHE_KEY);
        } catch (CacheException $e) {
            trigger_error(
                'Error retrieving the type dictionary from cache, the performance is degraded. Exception message: ' .
                $e->getMessage(),
                E_USER_WARNING
            );
            return null;
        }

        if ($dict instanceof ITypeDictionary) {
            if ($dict instanceof TrackingTypeDictionary) {
                // type usage watching is no more necessary - all types retrieved from cache are considered as being used
                $dict->disableTypeUsageWatching();
            }
            return $dict;
        } else {
            return null;
        }
    }


    //region ITypeControl

    public function getTypeRegister(): TypeRegister
    {
        if ($this->typeRegister === null) {
            $this->typeRegister = new TypeRegister();
        }

        return $this->typeRegister;
    }

    public function getTypeDictionary(): ITypeDictionary
    {
        if ($this->typeDictionary === null) {
            if ($this->preloadedTypeDictionary !== null) {
                $this->typeDictionary = $this->preloadedTypeDictionary;
                $this->preloadedTypeDictionary = null;
                $this->typeDictionaryLoadedFromCache = true;
            } else {
                $this->typeDictionary = $this->loadTypeDictionaryFromCache();
                if ($this->typeDictionary !== null) {
                    $this->typeDictionaryLoadedFromCache = true;
                } else {
                    $this->typeDictionary = $this->introspectTypeDictionary();
                    $this->typeDictionaryLoadedFromCache = false;
                }
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

        if ($this->options & self::OPTION_INTROSPECT_PLAIN_ARRAYS) {
            $compiler->setOption(IntrospectingTypeDictionaryCompiler::OPTION_PLAIN_ARRAYS);
        }

        $typeProvider = new TypeProviderList();
        $typeProvider->appendTypeProvider($this->getTypeRegister());
        $typeProvider->appendTypeProvider(Ivory::getTypeRegister());

        $dict = $compiler->compileTypeDictionary($typeProvider);

        return $dict;
    }

    private function setupTypeDictionarySearchPath(ITypeDictionary $typeDictionary): void
    {
        $sp = $this->connection->getConfig()->getEffectiveSearchPath();
        $typeDictionary->setTypeSearchPath($sp);
    }

    private function setupTypeDictionaryUndefinedHandler(): void
    {
        $handler = function (?int $oid, $schemaName, ?string $typeName, $value) {
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

            public function handleUndefinedType(?int $oid, $schemaName, ?string $typeName, $value): IType
            {
                return call_user_func($this->handler, $oid, $schemaName, $typeName, $value);
            }
        });
    }

    public function flushTypeDictionary(): void
    {
        $this->typeDictionary = null;
    }

    public function setTypeControlOption(int $option): void
    {
        $this->options = $this->options | $option;
    }

    public function unsetTypeControlOption(int $option): void
    {
        $this->options = $this->options & (~$option);
    }

    //endregion
}

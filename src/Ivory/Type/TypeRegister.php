<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;

/**
 * Collection of PHP type converters for recognized PostgreSQL base types.
 *
 * There are two levels of type registers: at the Ivory class and at a connection to a concrete database. At either
 * level, specific types and type loaders may be registered. Whenever a type converter is requested for a PostgreSQL
 * type, it is retrieved from the connection type register, then from the global register. If neither of them already
 * knows the requested type, type loaders registered at both the registers are consecutively tried to load the type
 * converter.
 *
 * For Ivory to recognize a new base type globally or locally for a given connection, the new type converter, or a whole
 * type loader may be registered at the corresponding type register, using {@link TypeRegister::registerType()} or
 * {@link TypeRegister::registerTypeLoader()}, respectively.
 *
 * The purpose of the type register is only to collect all the types and type loaders. Once any of the types is
 * requested by a connection, it may be cached for the whole script lifetime so that later type or type loader
 * registration changes are not reflected.
 */
class TypeRegister
{
    /** @var IType[][] already known types; map: schema name => map: type name => type converter object */
    private $types = [];
    /** @var ITypeLoader[] list of registered type loaders, in the definition order */
    private $typeLoaders = [];

    /**
     * Registers a type converter for a PostgreSQL base data type.
     *
     * If a type converter has already been registered for the given type, it gets dropped in favor of the new one.
     *
     * @param string $schemaName name of the PostgreSQL schema the type is defined in
     * @param string $typeName name of the PostgreSQL type
     * @param IType $type the type converter to register
     */
    public function registerType($schemaName, $typeName, IType $type)
    {
        if (!isset($this->types[$schemaName])) {
            $this->types[$schemaName] = [];
        }
        $this->types[$schemaName][$typeName] = $type;
    }

    /**
     * Unregisters a type converter, previously registered by {@link registerType()}.
     *
     * Either the name of the PostgreSQL schema and type name is given, in which case the converter for this concrete
     * type will get unregistered, or a type converter object is given, then any registrations of this type converter
     * will be dropped.
     *
     * @param string|IType $schemaNameOrTypeConverter
     *                                  name of the PostgreSQL schema the type to unregister is defined in, or type
     *                                    converter to unregister
     * @param string|null $typeName name of the PostgreSQL type to unregister, or <tt>null</tt> if an <tt>IType</tt>
     *                                object is provided in the first argument
     * @return bool whether the type has actually been unregistered (<tt>false</tt> if it was not registered)
     */
    public function unregisterType($schemaNameOrTypeConverter, $typeName = null)
    {
        if ($schemaNameOrTypeConverter instanceof IType) {
            if ($typeName !== null) {
                $msg = sprintf(
                    '$typeName is irrelevant when an %s object is given in the first argument',
                    IType::class
                );
                trigger_error($msg, E_USER_NOTICE);
            }

            $typeConverter = $schemaNameOrTypeConverter;
            $existed = false;
            foreach ($this->types as $sn => $types) {
                foreach ($types as $tn => $tc) {
                    if ($tc === $typeConverter) {
                        $existed = true;
                        unset($this->types[$sn][$tn]);
                    }
                }
                if (!$this->types[$sn]) {
                    unset($this->types[$sn]);
                }
            }
            return $existed;
        }
        else {
            $schemaName = $schemaNameOrTypeConverter;
            $existed = isset($this->types[$schemaName][$typeName]);
            unset($this->types[$schemaName][$typeName]);
            return $existed;
        }
    }

    /**
     * Registers a type loader, if not already registered.
     *
     * @param ITypeLoader $typeLoader type loader to register
     * @return bool whether the type loader has actually been registered (<tt>false</tt> if it was already registered
     *                before and thus this was a no-op)
     */
    public function registerTypeLoader(ITypeLoader $typeLoader)
    {
        $pos = array_search($typeLoader, $this->typeLoaders, true);
        if ($pos === false) {
            $this->typeLoaders[] = $typeLoader;
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Unregisters a previously registered type loader.
     *
     * @param ITypeLoader $typeLoader type loader to unregister
     * @return bool whether the type loader has actually been unregistered (<tt>false</tt> if it was not registered)
     */
    public function unregisterTypeLoader(ITypeLoader $typeLoader)
    {
        $pos = array_search($typeLoader, $this->typeLoaders, true);
        if ($pos !== false) {
            array_splice($this->typeLoaders, $pos, 1);
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Retrieves all the registered type loaders.
     *
     * @return ITypeLoader[] list of the registered type loaders, in the registration order
     */
    public function getTypeLoaders()
    {
        return $this->typeLoaders;
    }

    /**
     * Returns the type converter explicitly registered using {@link TypeRegister::registerType()}.
     *
     * @param string $schemaName name of the PostgreSQL schema to get the converter for
     * @param string $typeName name of the PostgreSQL type to get the converter for
     * @return IType|null converter for the requested type, or <tt>null</tt> if no converter was registered for the type
     */
    public function getType($schemaName, $typeName)
    {
        return (isset($this->types[$schemaName][$typeName]) ? $this->types[$schemaName][$typeName] : null);
        // PHP 7:
//        return ($this->types[$schemaName][$typeName] ?? null);
    }

    /**
     * Loads a type converter from the first registered type loader recognizing the type.
     *
     * @param string $schemaName name of the PostgreSQL schema to get the converter for
     * @param string $typeName name of the PostgreSQL type to get the converter for
     * @param IConnection $connection connection above which the type is to be loaded
     * @return IType|null converter for the requested type, or <tt>null</tt> if no loader recognizes the type
     */
    public function loadType($schemaName, $typeName, IConnection $connection)
    {
        foreach ($this->typeLoaders as $loader) {
            $tc = $loader->loadType($schemaName, $typeName, $connection);
            if ($tc !== null) {
                return $tc;
            }
        }
        return null;
    }
}

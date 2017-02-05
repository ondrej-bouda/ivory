<?php
namespace Ivory\Type;

use Ivory\Connection\IConnection;

/**
 * Collection of PHP type converters for recognized PostgreSQL base types.
 *
 * Type registers are used at two levels: at the Ivory class and at a connection to a concrete database. At either
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
 *
 * Besides types and type loaders, the type register also collects several type supplements:
 * - range canonical functions and their providers;
 * - abbreviations of qualified type names.
 */
class TypeRegister
{
    /** @var IType[][] already known types; map: schema name => map: type name => type converter object */
    private $types = [];
    /** @var ITypeLoader[] list of registered type loaders, in the definition order */
    private $typeLoaders = [];
    /**
     * @var array already known range canonical functions;
     *            map: schema name => map: function name => map: range subtype hash => function implementation
     */
    private $rangeCanonFuncs = [];
    /**
     * @var IRangeCanonicalFuncProvider[] list of registered range canonical function providers, in the definition order
     */
    private $rangeCanonFuncProviders = [];
    /** @var IType[] map: name => type converter */
    private $sqlPatternTypes = [];
    /** @var string[][] type name abbreviation => pair: schema name, type name */
    private $typeAbbreviations = [];

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
     * @return bool whether the type loader has actually been unregistered (<tt>false</tt> if it was not registered and
     *                thus this was a no-op)
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
     * Registers a range canonical function.
     *
     * If a function has already been registered with the same name and argument type, it gets dropped in favor of the
     * new one.
     *
     * @param string $schemaName name of the PostgreSQL schema the range canonical function is defined in
     * @param string $funcName name of the range canonical function
     * @param ITotallyOrderedType $subtype function argument type
     * @param IRangeCanonicalFunc $func the function to register
     */
    public function registerRangeCanonicalFunc($schemaName, $funcName, ITotallyOrderedType $subtype, IRangeCanonicalFunc $func)
    {
        if (!isset($this->rangeCanonFuncs[$schemaName])) {
            $this->rangeCanonFuncs[$schemaName] = [];
        }
        if (!isset($this->rangeCanonFuncs[$schemaName][$funcName])) {
            $this->rangeCanonFuncs[$schemaName][$funcName] = [];
        }
        $this->rangeCanonFuncs[$schemaName][$funcName][spl_object_hash($subtype)] = $func;
    }

    /**
     * Unregisters a range canonical function, previously registered by {@link registerRangeCanonicalFunc()}.
     *
     * Either the name of the PostgreSQL schema, function name, and argument type are given, in which case the
     * implementation for this concrete function will get unregistered, or a function object is given, then any
     * registrations of this function will be dropped.
     *
     * @param string|IRangeCanonicalFunc $schemaNameOrFunc
     *                                  name of the PostgreSQL schema the function to unregister is defined in, or
     *                                    function object to unregister
     * @param string|null $funcName name of the PostgreSQL function to unregister, or <tt>null</tt> if an
     *                                <tt>IRangeCanonicalFunc</tt> object is provided in the first argument
     * @param ITotallyOrderedType|null $subtype
     *                                  the argument type for the function to unregister;
     *                                  <tt>null</tt> to unregister all overloaded functions of the given name;
     *                                  <tt>null</tt> also skips this argument if an <tt>IRangeCanonicalFunc</tt> object
     *                                    is provided in the first argument
     * @return bool whether the function has actually been unregistered (<tt>false</tt> if it was not registered)
     */
    public function unregisterRangeCanonicalFunc($schemaNameOrFunc, $funcName = null, ITotallyOrderedType $subtype = null)
    {
        if ($schemaNameOrFunc instanceof IRangeCanonicalFunc) {
            if ($funcName !== null) {
                $msg = sprintf(
                    '$funcName is irrelevant when an %s object is given in the first argument',
                    IRangeCanonicalFunc::class
                );
                trigger_error($msg, E_USER_NOTICE);
            }
            if ($subtype !== null) {
                $msg = sprintf(
                    '$subtype is irrelevant when an %s object is given in the first argument',
                    IRangeCanonicalFunc::class
                );
                trigger_error($msg, E_USER_NOTICE);
            }

            $func = $schemaNameOrFunc;
            $existed = false;
            foreach ($this->rangeCanonFuncs as $sn => $funcs) {
                foreach ($funcs as $fn => $overloads) {
                    foreach ($overloads as $h => $f) {
                        if ($f === $func) {
                            $existed = true;
                            unset($this->rangeCanonFuncs[$sn][$fn][$h]);
                        }
                    }
                    if (!$this->rangeCanonFuncs[$sn][$fn]) {
                        unset($this->rangeCanonFuncs[$sn][$fn]);
                    }
                }
                if (!$this->rangeCanonFuncs[$sn]) {
                    unset($this->rangeCanonFuncs[$sn]);
                }
            }
            return $existed;
        }
        else {
            $schemaName = $schemaNameOrFunc;
            if ($subtype === null) {
                $existed = isset($this->rangeCanonFuncs[$schemaName][$funcName]);
                unset($this->rangeCanonFuncs[$schemaName][$funcName]);
                return $existed;
            }
            else {
                $h = spl_object_hash($subtype);
                $existed = isset($this->rangeCanonFuncs[$schemaName][$funcName][$h]);
                unset($this->rangeCanonFuncs[$schemaName][$funcName][$h]);
                return $existed;
            }
        }
    }

    /**
     * Registers a range canonical function provider, if not already registered.
     *
     * @param IRangeCanonicalFuncProvider $provider provider to register
     * @return bool whether the provider has actually been registered (<tt>false</tt> if it was already registered
     *                before and thus this was a no-op)
     */
    public function registerRangeCanonicalFuncProvider(IRangeCanonicalFuncProvider $provider)
    {
        $pos = array_search($provider, $this->rangeCanonFuncProviders, true);
        if ($pos === false) {
            $this->rangeCanonFuncProviders[] = $provider;
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Unregisters a previously registered range canonical function provider.
     *
     * @param IRangeCanonicalFuncProvider $provider provider to unregister
     * @return bool whether the provider has actually been unregistered (<tt>false</tt> if it was not registered and
     *                thus this was a no-op)
     */
    public function unregisterRangeCanonicalFuncProvider(IRangeCanonicalFuncProvider $provider)
    {
        $pos = array_search($provider, $this->rangeCanonFuncProviders, true);
        if ($pos !== false) {
            array_splice($this->rangeCanonFuncProviders, $pos, 1);
            return true;
        }
        else {
            return false;
        }
    }

    /**
     * Registers a type converter to be used for serializing parameters of {@link Ivory\Lang\SqlPattern\SqlPattern}
     * typed placeholders.
     *
     * This method is only useful for registering special converters, such as `sql`. Common data types are registered
     * automatically by their name, abbreviations are registered with {@link registerSqlPatternTypeAbbreviation()}.
     *
     * If a type converter has already been registered with the given name, it gets dropped in favor of the new one.
     *
     * @param string $name
     * @param IType $type
     */
    public function registerSqlPatternType(string $name, IType $type)
    {
        $this->sqlPatternTypes[$name] = $type;
    }

    public function unregisterSqlPatternType(string $name)
    {
        unset($this->sqlPatternTypes[$name]);
    }

    /**
     * Registers an abbreviation for a qualified type name.
     *
     * If the abbreviation has already been registered before, it gets dropped in favor of the new one.
     *
     * @param string $abbreviation abbreviation for the type name
     * @param string $schemaName name of schema a referred-to type is defined in
     * @param string $typeName name of type the abbreviation refers to
     */
    public function registerTypeAbbreviation(string $abbreviation, string $schemaName, string $typeName)
    {
        $this->typeAbbreviations[$abbreviation] = [$schemaName, $typeName];
    }

    public function unregisterTypeAbbreviation(string $abbreviation)
    {
        unset($this->typeAbbreviations[$abbreviation]);
    }

    /**
     * @return IType[] map: name => type converter
     */
    public function getSqlPatternTypes()
    {
        return $this->sqlPatternTypes;
    }

    /**
     * @return string[][] map: abbreviation => pair: schema name, type name
     */
    public function getTypeAbbreviations()
    {
        return $this->typeAbbreviations;
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
        return ($this->types[$schemaName][$typeName] ?? null);
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

    /**
     * Retrieves all the registered range canonical function providers.
     *
     * @return IRangeCanonicalFuncProvider[] list of the registered range canonical function providers, in the
     *                                         registration order
     */
    public function getRangeCanonicalFuncProviders()
    {
        return $this->rangeCanonFuncProviders;
    }

    /**
     * Returns the range canonical function explicitly registered using
     * {@link TypeRegister::registerRangeCanonicalFunc()}.
     *
     * @param string $schemaName name of the PostgreSQL schema to get the function from
     * @param string $funcName name of the PostgreSQL function
     * @param ITotallyOrderedType $subtype function argument type
     * @return IRangeCanonicalFunc|null the requested function, or <tt>null</tt> if no such function was registered
     */
    public function getRangeCanonicalFunc($schemaName, $funcName, ITotallyOrderedType $subtype)
    {
        return ($this->rangeCanonFuncs[$schemaName][$funcName][spl_object_hash($subtype)] ?? null);
    }

    /**
     * Provides a range canonical function by the first registered provider recognizing it.
     *
     * @param string $schemaName name of the PostgreSQL schema to get the function from
     * @param string $funcName name of the PostgreSQL function to provide
     * @param ITotallyOrderedType $subtype function argument type
     * @return IRangeCanonicalFunc|null the requested function, or <tt>null</tt> if no provider recognizes the function
     */
    public function provideRangeCanonicalFunc($schemaName, $funcName, ITotallyOrderedType $subtype)
    {
        foreach ($this->rangeCanonFuncProviders as $provider) {
            $func = $provider->provideCanonicalFunc($schemaName, $funcName, $subtype);
            if ($func !== null) {
                return $func;
            }
        }
        return null;
    }
}

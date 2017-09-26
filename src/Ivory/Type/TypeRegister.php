<?php
declare(strict_types=1);

namespace Ivory\Type;

/**
 * Collection of PHP type objects and their supplements for recognized PostgreSQL types.
 *
 * Type registers serve as the way to define the type system behaviour. Their purpose is to collect:
 * - types and type loaders,
 * - range canonical functions and their providers,
 * - abbreviations of qualified type names, and
 * - rules for recognizing types from values.
 *
 * By {@link registerType() registering a type object} or a whole {@link registerTypeLoader() type loader}, one may
 * define the processing of any custom PostgreSQL type not yet covered by Ivory or override any builtin type object with
 * a custom one. Likewise, abbreviations of type names may be defined at will - they will be recognized in
 * {@link \Ivory\Lange\SqlPattern\SqlPattern SQL patterns} when composing an SQL query or command.
 *
 * The type registers are used at two levels: a *global* one at the Ivory class and a *local* one at the connection to
 * a concrete database. Definitions from the local type register are preferred. Only when the type or abbreviation is
 * not recognized by the local type register, the global type register is consulted. The same applies when recognizing
 * the type from a PHP value - first, the local type register rules are tried, followed by the global type register
 * rules.
 *
 * The type registers are not directly used when Ivory works with types. Instead, they merely serve as a basis for an
 * {@link ITypeDictionary}, which is compiled automatically when first needed, and is usually cached for the whole
 * script lifetime. Thus, **later registration changes of types, type loaders or type supplements are not reflected**.
 */
class TypeRegister implements ITypeProvider
{
    /** @var IType[][] already known types; map: schema name => map: type name => type object */
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
    /** @var IValueSerializer[] map: name => value serializer */
    private $valueSerializers = [];
    /** @var string[][] type name abbreviation => pair: schema name, type name */
    private $typeAbbreviations = [];
    /** @var string[][] PHP data type => pair: schema name, type name */
    private $typeRecognitionRules = [];

    /**
     * Registers a type.
     *
     * If a type has already been registered under the same qualified name, it gets dropped in favor of this new one.
     *
     * @param IType $type the type to register
     */
    public function registerType(IType $type): void
    {
        $schemaName = $type->getSchemaName();
        $typeName = $type->getName();

        if (!isset($this->types[$schemaName])) {
            $this->types[$schemaName] = [];
        }
        $this->types[$schemaName][$typeName] = $type;
    }

    /**
     * Unregisters a type, previously registered by {@link registerType()}.
     *
     * The type to unregister may either be given itself, or by the schema and type name.
     *
     * @param string|IType $schemaNameOrType
     *                                  name of the PostgreSQL schema the type to unregister is defined in, or the type
     *                                    itself to unregister
     * @param string|null $typeName name of the type to unregister, or <tt>null</tt> if an <tt>IType</tt> object is
     *                                provided in the first argument
     * @return bool whether the type has actually been unregistered (<tt>false</tt> if it was not registered)
     */
    public function unregisterType($schemaNameOrType, ?string $typeName = null): bool
    {
        if ($schemaNameOrType instanceof IType) {
            if ($typeName !== null) {
                $msg = sprintf(
                    '$typeName is irrelevant when an %s object is given in the first argument',
                    IType::class
                );
                trigger_error($msg, E_USER_NOTICE);
            }

            $schemaName = $schemaNameOrType->getSchemaName();
            $typeName = $schemaNameOrType->getName();
        } else {
            $schemaName = $schemaNameOrType;
            if ($typeName === null) {
                trigger_error('$typeName not given', E_USER_WARNING);
                return false;
            }
        }

        $existed = isset($this->types[$schemaName][$typeName]);
        unset($this->types[$schemaName][$typeName]);
        return $existed;
    }

    /**
     * Registers a type loader, if not already registered.
     *
     * @param ITypeLoader $typeLoader type loader to register
     * @return bool whether the type loader has actually been registered (<tt>false</tt> if it was already registered
     *                before and thus this was a no-op)
     */
    public function registerTypeLoader(ITypeLoader $typeLoader): bool
    {
        $pos = array_search($typeLoader, $this->typeLoaders, true);
        if ($pos === false) {
            $this->typeLoaders[] = $typeLoader;
            return true;
        } else {
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
    public function unregisterTypeLoader(ITypeLoader $typeLoader): bool
    {
        $pos = array_search($typeLoader, $this->typeLoaders, true);
        if ($pos !== false) {
            array_splice($this->typeLoaders, $pos, 1);
            return true;
        } else {
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
    public function registerRangeCanonicalFunc(
        string $schemaName,
        string $funcName,
        ITotallyOrderedType $subtype,
        IRangeCanonicalFunc $func
    ): void {
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
     * Either the name of the PostgreSQL schema, function name and argument type are given, in which case the
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
    public function unregisterRangeCanonicalFunc(
        $schemaNameOrFunc,
        ?string $funcName = null,
        ?ITotallyOrderedType $subtype = null
    ): bool {
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
        } else {
            $schemaName = $schemaNameOrFunc;
            if ($subtype === null) {
                $existed = isset($this->rangeCanonFuncs[$schemaName][$funcName]);
                unset($this->rangeCanonFuncs[$schemaName][$funcName]);
                return $existed;
            } else {
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
    public function registerRangeCanonicalFuncProvider(IRangeCanonicalFuncProvider $provider): bool
    {
        $pos = array_search($provider, $this->rangeCanonFuncProviders, true);
        if ($pos === false) {
            $this->rangeCanonFuncProviders[] = $provider;
            return true;
        } else {
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
    public function unregisterRangeCanonicalFuncProvider(IRangeCanonicalFuncProvider $provider): bool
    {
        $pos = array_search($provider, $this->rangeCanonFuncProviders, true);
        if ($pos !== false) {
            array_splice($this->rangeCanonFuncProviders, $pos, 1);
            return true;
        } else {
            return false;
        }
    }

    /**
     * Registers value serializer to be used for serializing parameters of {@link Ivory\Lang\SqlPattern\SqlPattern}
     * typed placeholders.
     *
     * This method is only useful for registering special serializers, such as `sql`. Common data types are registered
     * automatically by their name, their abbreviations registered with {@link registerTypeAbbreviation()}.
     *
     * If a value serializer has already been registered with the given name, it gets dropped in favor of the new one.
     *
     * @param string $name
     * @param IValueSerializer $valueSerializer
     */
    public function registerValueSerializer(string $name, IValueSerializer $valueSerializer): void
    {
        $this->valueSerializers[$name] = $valueSerializer;
    }

    public function unregisterValueSerializer(string $name): void
    {
        unset($this->valueSerializers[$name]);
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
    public function registerTypeAbbreviation(string $abbreviation, string $schemaName, string $typeName): void
    {
        $this->typeAbbreviations[$abbreviation] = [$schemaName, $typeName];
    }

    /**
     * Unregisters a previously registered abbreviation for a type name.
     *
     * If no such abbreviation has been registered, nothing is done.
     *
     * @param string $abbreviation abbreviation for a type name
     */
    public function unregisterTypeAbbreviation(string $abbreviation): void
    {
        unset($this->typeAbbreviations[$abbreviation]);
    }

    /**
     * Adds a new rule for recognizing type from a PHP value.
     *
     * The rule tells to use the type object registered for `$schemaName.$typeName` type whenever a value of the
     * specified data type is given. The data type may be given by the string containing either:
     * - `'bool'`, `'int'`, `'float'`, `'string'`, or `'null'`, specifying to use the type object for values of the
     *   corresponding scalar types; or
     * - `'array'`, specifying the type object to used for serializing *unrecognized* arrays (see below for details
     *   regarding recognition of types for array values); or
     * - fully-qualified name of a class or interface.
     *
     * The data type of a presented PHP value is not matched just against the given data type. Subtypes are also
     * recognized. The mechanism is as follows:
     * 1. an exact match is attempted - if found, the type object for the recognized type is used;
     * 2. otherwise, supertypes of the PHP value are consecutively tried and the first match is used:
     *    - `float` is, for this purpose, considered as a supertype of `int`;
     *    - parent classes of an object are unrolled consecutively from the closest parent to the top-level superclass;
     *    - if parent classes do not make a match, then all interfaces the class of the PHP value implements, are tried
     *      in the alphabetical ordered (note this also includes interfaces extended by the ones actually implemented by
     *      the class of the PHP value.
     *
     * Arrays are recognized automatically - their type is recognized according to the first non-array, non-null element
     * found in the array. Recall that PHP arrays may hold elements of various types while PostgreSQL limits arrays to
     * be of just a single type. The type is recognized by taking the first non-null element in the array. If it is an
     * array, the recognition procedure recursively continues on it. The whole array value is then treated by the array
     * type object with its element type set to the recognized type. If the type is not recognized (empty array or array
     * only containing null values), the rule specified for `array` is used, if defined.
     *
     * Any previously defined rule for the same data type (`$dataType`) is dropped in favor of the new one.
     *
     * Note the rules are case sensitive.
     *
     * @internal Ivory design note: Alternatively, the data types recognition might be defined by the registered type
     * objects. That would not work for two reasons: multiple type objects might collide about the recognized type
     * and, more importantly, the same type object class is registered for multiple types (e.g., IntegerType) and
     * Ivory could not decide which type object instance to actually use.
     * @internal Ivory design note: Regarding the alphabetical order of interfaces matched against the rules: a more
     * sophisticated preference system might be used. We prefer simplicity, though, over perfection (in the perfect
     * system, the fact which parent declared implementing which interfaces, should be considered, complicated with
     * parent interfaces...).
     *
     * @param string $dataType either one of <tt>boolean</tt>, <tt>integer</tt>, <tt>double</tt>, <tt>string</tt>, and
     *                           <tt>null</tt>, or a fully-qualified name of a PHP class or interface
     * @param string $schemaName name of schema the recognized type is defined in
     * @param string $typeName name of the recognized type
     */
    public function addTypeRecognitionRule(string $dataType, string $schemaName, string $typeName): void
    {
        $this->typeRecognitionRules[$dataType] = [$schemaName, $typeName];
    }

    /**
     * Removes rule for the given PHP data type
     *
     * If no such rule has been added, nothing is done.
     *
     * @param string $dataType
     */
    public function removeTypeRecognitionRule(string $dataType): void
    {
        unset($this->typeRecognitionRules[$dataType]);
    }


    /**
     * Returns the type object explicitly registered using {@link TypeRegister::registerType()}.
     *
     * @param string $schemaName name of the PostgreSQL schema to get the type object for
     * @param string $typeName name of the PostgreSQL type to get the type object for
     * @return IType|null the requested type object, or <tt>null</tt> if no corresponding type is registered
     */
    public function getType(string $schemaName, string $typeName): ?IType
    {
        return ($this->types[$schemaName][$typeName] ?? null);
    }

    /**
     * Retrieves all the registered type loaders.
     *
     * @return ITypeLoader[] list of the registered type loaders, in the registration order
     */
    public function getTypeLoaders(): array
    {
        return $this->typeLoaders;
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
    public function getRangeCanonicalFunc(
        string $schemaName,
        string $funcName,
        ITotallyOrderedType $subtype
    ): ?IRangeCanonicalFunc
    {
        return ($this->rangeCanonFuncs[$schemaName][$funcName][spl_object_hash($subtype)] ?? null);
    }

    /**
     * Retrieves all the registered range canonical function providers.
     *
     * @return IRangeCanonicalFuncProvider[] list of the registered range canonical function providers, in the
     *                                         registration order
     */
    public function getRangeCanonicalFuncProviders(): array
    {
        return $this->rangeCanonFuncProviders;
    }


    //region ITypeProvider

    public function provideType(string $schemaName, string $typeName): ?IType
    {
        $type = $this->getType($schemaName, $typeName);
        if ($type !== null) {
            return $type;
        }

        foreach ($this->typeLoaders as $loader) {
            $type = $loader->loadType($schemaName, $typeName);
            if ($type !== null) {
                return $type;
            }
        }

        return null;
    }

    public function provideRangeCanonicalFunc(
        string $schemaName,
        string $funcName,
        ITotallyOrderedType $subtype
    ): ?IRangeCanonicalFunc
    {
        $func = $this->getRangeCanonicalFunc($schemaName, $funcName, $subtype);
        if ($func !== null) {
            return $func;
        }

        foreach ($this->rangeCanonFuncProviders as $provider) {
            $func = $provider->provideCanonicalFunc($schemaName, $funcName, $subtype);
            if ($func !== null) {
                return $func;
            }
        }

        return null;
    }

    public function getTypeRecognitionRules(): array
    {
        return $this->typeRecognitionRules;
    }

    public function getValueSerializers(): array
    {
        return $this->valueSerializers;
    }

    public function getTypeAbbreviations(): array
    {
        return $this->typeAbbreviations;
    }

    //endregion
}

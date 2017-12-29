<?php
declare(strict_types=1);
namespace Ivory\Connection;

use Ivory\Type\ITypeDictionary;
use Ivory\Type\TypeRegister;

interface ITypeControl
{
    const TYPE_DICTIONARY_CACHE_KEY = ITypeDictionary::class;

    /**
     * @return TypeRegister the type register local to this connection
     */
    function getTypeRegister(): TypeRegister;

    /**
     * @return ITypeDictionary type dictionary valid for this connection
     */
    function getTypeDictionary(): ITypeDictionary;

    /**
     * Flushes the type dictionary currently in use, leading to loading a fresh new type dictionary.
     *
     * Useful when the data types change during the script execution in such a way Ivory is unable to detect it.
     */
    function flushTypeDictionary(): void;

    /**
     * Sets an implementation-specific option, driving behaviour of the type control.
     *
     * @param int $option
     */
    function setTypeControlOption(int $option): void;

    /**
     * Unsets an implementation-specific option, driving behaviour of the type control.
     *
     * @param int $option
     */
    function unsetTypeControlOption(int $option): void;
}

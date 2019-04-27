<?php
declare(strict_types=1);
namespace Ivory;

/**
 * Any database object (e.g., table, view, data type) which has a name.
 */
interface INamedDbObject
{
    /**
     * @return string name of this object
     */
    function getName(): string;

    /**
     * @return string|null name of schema this object is defined in, or <tt>null</tt> if define outside of any schema
     */
    function getSchemaName(): ?string;
}

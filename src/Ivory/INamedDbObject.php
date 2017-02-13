<?php
namespace Ivory;

/**
 * Any database object (e.g., table, view, data type) which has a name.
 */
interface INamedDbObject
{
    /**
     * @return string name of this object
     */
    function getName();

    /**
     * @return string name of schema this object is defined in
     */
    function getSchemaName();
}

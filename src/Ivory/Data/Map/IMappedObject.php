<?php
declare(strict_types=1);
namespace Ivory\Data\Map;

/**
 * An object (relation, column, tuple, etc.) mapped by custom keys, usually by values of one of columns of a relation.
 *
 * A mapped object behaves like the object itself, except it is in fact a readonly-`ArrayAccess`ible container allowing
 * access to the objects being held using the mapping keys as the `ArrayAccess` offsets. Moreover, the container is
 * `Traversable`, iterating over the mapping keys and their corresponding objects.
 */
interface IMappedObject extends \ArrayAccess, \Traversable, \Countable
{

}

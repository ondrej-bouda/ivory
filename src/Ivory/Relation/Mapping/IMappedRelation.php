<?php
namespace Ivory\Relation\Mapping;

use Ivory\Relation\IRelation;

/**
 * Relation mapped by custom keys, usually by values of one of the columns of the originating relation.
 *
 * Note there may only be exactly one tuple per mapping key. If duplicates are to be permitted,
 * {@link IMultimappedRelation} shall be considered instead.
 *
 * A mapped relation behaves like any other {@link IRelation}, it just maintains the correspondence of the keys to the
 * relation tuples. The only exceptions are as follows:
 * 1. Traversal: the mapping keys are returned as the traversal keys instead of mere list positions.
 * 2. {@link toArray()} returns an associative array, using the mapping keys as the returned array keys for the items.
 *
 * Moreover, a mapped relation behaves like a readonly-`ArrayAccess`ible object, allowing access to {@link ITuple}s
 * using the mapping keys as the `ArrayAccess` offsets.
 */
interface IMappedRelation extends IRelation, IMappedObject
{
}

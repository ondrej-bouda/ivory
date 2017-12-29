<?php
declare(strict_types=1);
namespace Ivory\Type\Postgresql;

use Ivory\NamedDbObject;

/**
 * Represents an unnamed composite type.
 *
 * {@inheritdoc}
 *
 * Unnamed composite types are typically returned from `ROW()` expressions or from queries which construct the
 * composition on-the-fly, such as `WITH t (foo,foo2) AS (VALUES ('bar','baz')) SELECT t FROM t`, which returns a value
 * `('bar','baz')` of ad-hoc composite type `("foo"::text, "foo2"::text)`.
 */
class AdHocCompositeType extends CompositeType
{
    use NamedDbObject;

    public function __construct(string $schemaName, string $typeName)
    {
        parent::__construct();

        $this->setName($schemaName, $typeName);
    }
}

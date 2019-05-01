<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\NotImplementedException;

/**
 * Base for type-strict composite values.
 *
 * Specializes the general {@link Composite} class such that:
 * - the constructor expects a map of values of all attributes the composite defines;
 * - the attribute getter emits a warning upon accessing an undefined attribute.
 */
abstract class StrictComposite extends Composite
{
    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function fromMap(array $valueMap): Composite
    {
        throw new NotImplementedException(
            __METHOD__ . ' must be re-implemented by ' . static::class . ' - base ' . Composite::class .
            ' implementation cannot be used'
        );
    }

    public function __get($name)
    {
        $val = parent::__get($name);

        if ($val === null && !array_key_exists($name, $this->toMap())) {
            trigger_error(
                sprintf('Accessing an undefined attribute "%s" of %s', $name, get_class($this)),
                E_USER_WARNING
            );
        }

        return $val;
    }
}

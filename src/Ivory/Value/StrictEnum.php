<?php
declare(strict_types=1);
namespace Ivory\Value;

use Ivory\Exception\IncomparableException;
use Ivory\Value\Alg\IComparable;

/**
 * Base for enumeration classes, implementing a user-defined PostgreSQL enumeration type.
 *
 * For the subclasses, it is sufficient to implement just the {@link getValues()} method, which should return the list
 * of the enumeration values. E.g.:
 * <code>
 * <?php
 * class Planet extends StrictEnum
 * {
 *     protected static function getValues()
 *     {
 *         return ['Mercury', 'Venus', 'Earth', 'Mars', 'Jupiter', 'Saturn', 'Uranus', 'Neptune'];
 *     }
 * }
 * </code>
 *
 * Individual items are retrieved using the static method call syntax. Objects of the subclass are returned, which are
 * comparable to others:
 * <code>
 * <?php
 * $mars = Planet::Mars();
 * assert($mars->compareTo(Planet::Jupiter()) < 0);
 * </code>
 *
 * Note, in order to let the IDE recognize the items, declare them in the class PHPDoc block using the &#64;method
 * annotation, such as: &#64;method static Planet Mercury()
 */
abstract class StrictEnum implements IComparable
{
    private static $flipped = [];
    private $value;

    /**
     * Returns the list of enumeration values as defined in the corresponding PostgreSQL enumeration type (including
     * mutual order).
     *
     * Note the values are case sensitive.
     *
     * @return string[]
     */
    abstract protected static function getValues(): array;

    private static function getValueMap(): array
    {
        if (!isset(self::$flipped[static::class])) {
            self::$flipped[static::class] = array_flip(static::getValues());
        }
        return self::$flipped[static::class];
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return new static($name);
    }

    final public function __construct(string $value)
    {
        if (!isset(self::getValueMap()[$value])) {
            throw new \InvalidArgumentException("`$value` is not among the defined enumeration values");
        }

        $this->value = $value;
    }

    final public function getValue(): string
    {
        return $this->value;
    }

    final public function equals($other): bool
    {
        return (
            $other instanceof StrictEnum &&
            $other->value == $this->value &&
            get_class($other) == static::class
        );
    }

    final public function compareTo($other): int
    {
        if ($other === null) {
            throw new \InvalidArgumentException();
        }
        if (!$other instanceof StrictEnum || get_class($other) != static::class) {
            throw new IncomparableException();
        }
        return self::getValueMap()[$this->value] - self::getValueMap()[$other->value];
    }

    final public function __toString()
    {
        return $this->value;
    }
}

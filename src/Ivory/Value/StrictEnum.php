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
 *     protected static function getValues(): array
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
 * assert($mars == Planet::Mars());
 * assert($mars->compareTo(Planet::Jupiter()) < 0);
 * </code>
 *
 * Due to PHP `==` operator taking taking the class into consideration, enumeration objects may safely be compared
 * including their type:
 * <code>
 * <?php
 * class ChocolateBar extends StrictEnum
 * {
 *     protected static function getValues(): array
 *     {
 *         return ['Mars', 'Snickers', 'Twix'];
 *     }
 * }
 *
 * assert(ChocolateBar::Mars() == ChocolateBar::Mars());
 * assert(Planet::Mars() != ChocolateBar::Mars());
 * </code>
 *
 * Note, in order to let the IDE recognize the items, declare them in the class PHPDoc block using the &#64;method
 * annotation, such as: &#64;method static Planet Mercury()
 */
abstract class StrictEnum implements IComparable
{
    private static $valueMap = [];
    private $offset;

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
        if (!isset(self::$valueMap[static::class])) {
            self::$valueMap[static::class] = array_flip(static::getValues());
        }
        return self::$valueMap[static::class];
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return new static($name);
    }

    final public function __construct(string $value)
    {
        $valueMap = self::getValueMap();
        if (!isset($valueMap[$value])) {
            throw new \InvalidArgumentException("`$value` is not among the defined enumeration values");
        }

        $this->offset = $valueMap[$value];
    }

    final public function getValue(): string
    {
        return static::getValues()[$this->offset];
    }

    final public function equals($other): bool
    {
        return ($this == $other);
    }

    final public function compareTo($other): int
    {
        if ($other === null) {
            throw new \InvalidArgumentException();
        }
        if (!$other instanceof StrictEnum || get_class($other) != static::class) {
            throw new IncomparableException();
        }
        return $this->offset - $other->offset;
    }

    final public function __toString()
    {
        return $this->getValue();
    }

    public function __debugInfo()
    {
        return [
            'offset' => $this->offset,
            'value' => $this->getValue(),
        ];
    }
}

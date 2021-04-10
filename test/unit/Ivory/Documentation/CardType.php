<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Exception\IncomparableException;
use Ivory\Type\ITotallyOrderedType;
use Ivory\Type\TypeBase;
use Ivory\Value\Alg\IDiscreteStepper;
use Ivory\Value\Alg\IValueComparator;

/**
 * Type converter for an imaginary "card" type.
 *
 * Cards are represented by two-character strings. The first character holds the rank, the second identifies the color.
 *
 * The converter is to be used as the comparator and discrete stepper for the card values.
 */
class CardType extends TypeBase implements ITotallyOrderedType
{
    const CARD_ORDER = [
        '2S', '2H', '2D', '2C', '3S', '3H', '3D', '3C', '4S', '4H', '4D', '4C', '5S', '5H', '5D', '5C',
        '6S', '6H', '6D', '6C', '7S', '7H', '7D', '7C', '8S', '8H', '8D', '8C', '9S', '9H', '9D', '9C',
        'TS', 'TH', 'TD', 'TC', 'JS', 'JH', 'JD', 'JC', 'QS', 'QH', 'QD', 'QC', 'KS', 'KH', 'KD', 'KC',
        'AS', 'AH', 'AD', 'AC',
    ];

    private static $valueComparator = null;
    private static $discreteStepper = null;

    public static function provideValueComparator(): IValueComparator
    {
        if (self::$valueComparator === null) {
            self::$valueComparator = new class implements IValueComparator
            {
                public function compareValues($a, $b): int
                {
                    if ($a === null || $b === null) {
                        throw new \InvalidArgumentException();
                    }

                    $aPos = array_search($a, CardType::CARD_ORDER);
                    $bPos = array_search($b, CardType::CARD_ORDER);

                    if ($aPos !== false && $bPos !== false) {
                        return ($aPos - $bPos);
                    } else {
                        throw new IncomparableException();
                    }
                }
            };
        }
        return self::$valueComparator;
    }

    public static function provideDiscreteStepper(): IDiscreteStepper
    {
        if (self::$discreteStepper === null) {
            self::$discreteStepper = new class implements IDiscreteStepper
            {
                public function step(int $delta, $value): string
                {
                    $pos = array_search($value, CardType::CARD_ORDER);
                    if ($pos === false) {
                        throw new \InvalidArgumentException();
                    }

                    $requestedPos = $pos + $delta;
                    $newPos = max(0, min(count(CardType::CARD_ORDER) - 1, $requestedPos));

                    return CardType::CARD_ORDER[$newPos];
                }
            };
        }
        return self::$discreteStepper;
    }

    public function parseValue(string $extRepr): string
    {
        return $extRepr;
    }

    public function serializeValue($val, bool $strictType = true): string
    {
        if ($val === null) {
            return ($strictType ? 'NULL::card' : 'NULL');
        }
        if (preg_match('~^[23456789TJQKA][CDHS]$~', $val)) {
            return ($strictType ? 'card ' : '') . "'" . $val . "'";
        } else {
            throw new \InvalidArgumentException();
        }
    }
}

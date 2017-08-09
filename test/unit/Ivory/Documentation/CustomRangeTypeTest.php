<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\Exception\IncomparableException;
use Ivory\Ivory;
use Ivory\IvoryTestCase;
use Ivory\StdCoreFactory;
use Ivory\Value\Alg\ComparisonUtils;
use Ivory\Value\Alg\IValueComparator;
use Ivory\Value\Range;

class CustomRangeTypeTest extends IvoryTestCase
{
    public function testLtreeTotallyOrderedDefinition()
    {
        $conn = $this->createNewIvoryConnection(self::class);
        $conn->flushTypeDictionary();

        $typeRegister = $conn->getTypeRegister();
        $typeRegister->registerType(new LtreeTotallyOrderedType());

        $tx = $conn->startTransaction();
        try {
            $conn->command('CREATE EXTENSION IF NOT EXISTS ltree');

            $ltrees = $conn->querySingleTuple(
                "SELECT ltree 'A.1.b' AS lower, ltree 'A.2.d' AS upper"
            );
            $range = Range::fromBounds($ltrees->lower, $ltrees->upper);

            self::assertTrue(
                $range->containsElement(LtreeComparable::fromArray(['A', '1', 'f']))
            );
            self::assertFalse(
                $range->containsElement(LtreeComparable::fromArray(['A', '2', 'd']))
            );
        } finally {
            $tx->rollback();
        }
    }

    public function testDateTimeRangeUsingGlobalComparator()
    {
        try {
            Range::fromBounds(
                \DateTime::createFromFormat('Y-m-d H:i:s', '2018-01-15 16:30:00'),
                \DateTime::createFromFormat('Y-m-d H:i:s', '2018-01-15 17:30:00')
            );
            self::fail('Out of the box, ' . \DateTime::class . ' objects are incomparable in Ivory');
        } catch (IncomparableException $e) {
        }

        $origCoreFactory = Ivory::getCoreFactory();
        Ivory::flushDefaultValueComparator();

        try {
            Ivory::setCoreFactory(new class extends StdCoreFactory
            {
                /** @noinspection PhpMissingParentCallCommonInspection */
                public function createDefaultValueComparator(): IValueComparator
                {
                    return new class implements IValueComparator
                    {
                        public function compareValues($a, $b): int
                        {
                            if ($a instanceof \DateTimeInterface && $b instanceof \DateTimeInterface) {
                                return ($a->getTimestamp() - $b->getTimestamp());
                            } else {
                                return ComparisonUtils::compareValues($a, $b);
                            }
                        }
                    };
                }
            });

            $dateTimeRange = Range::fromBounds(
                \DateTime::createFromFormat('Y-m-d H:i:s', '2018-01-15 16:30:00'),
                \DateTime::createFromFormat('Y-m-d H:i:s', '2018-01-15 17:30:00')
            );
            self::assertTrue(
                $dateTimeRange->containsElement(
                    \DateTime::createFromFormat('Y-m-d H:i:s', '2018-01-15 17:00:00')
                )
            );
        } finally {
            Ivory::setCoreFactory($origCoreFactory);
            Ivory::flushDefaultValueComparator();
        }
    }

    public function testSpecialRangeSubclass()
    {
        $cardType = new CardType('public', 'card');
        $cardRangeType = new CardRangeType('public', 'cardrange', $cardType);

        $conn = $this->getIvoryConnection();

        $typeRegister = $conn->getTypeRegister();
        $typeRegister->registerType($cardType);
        $typeRegister->registerType($cardRangeType);

        $tx = $conn->startTransaction();
        try {
            // to test custom ranges, mimick the hypothetical "card" type with an enumeration
            $conn->command(
                "CREATE TYPE card AS ENUM (
                     '2S', '2H', '2D', '2C', '3S', '3H', '3D', '3C', '4S', '4H', '4D', '4C', '5S', '5H', '5D', '5C',
                     '6S', '6H', '6D', '6C', '7S', '7H', '7D', '7C', '8S', '8H', '8D', '8C', '9S', '9H', '9D', '9C',
                     'TS', 'TH', 'TD', 'TC', 'JS', 'JH', 'JD', 'JC', 'QS', 'QH', 'QD', 'QC', 'KS', 'KH', 'KD', 'KC',
                     'AS', 'AH', 'AD', 'AC'                     
                 )"
            );
            $conn->command(
                'CREATE TYPE cardrange AS RANGE (
                     SUBTYPE = card
                 )'
                // note the discrete type should also have a canonical function - which we cannot define here, though
            );

            $range = $conn->querySingleValue(
                "SELECT cardrange('7S', NULL) * %cardrange",
                CardRange::fromBounds('3S', 'JS')
            );
            self::assertInstanceOf(CardRange::class, $range);

            assert($range instanceof CardRange);
            self::assertSame(
                ['7S', 'TC'],
                $range->toBounds('[]')
            );
        } finally {
            $tx->rollback();
        }
    }
}

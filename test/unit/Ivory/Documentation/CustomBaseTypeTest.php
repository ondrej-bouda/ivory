<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\IvoryTestCase;

class CustomBaseTypeTest extends IvoryTestCase
{
    public function testLtreeDefinition()
    {
        $conn = $this->createNewIvoryConnection(self::class);
        $conn->flushTypeDictionary();

        $typeRegister = $conn->getTypeRegister();
        $typeRegister->registerType(new LtreeType());

        $tx = $conn->startTransaction();
        try {
            $conn->command('CREATE EXTENSION IF NOT EXISTS ltree');

            $someLtree = $conn->querySingleValue('SELECT %ltree', Ltree::fromArray(['A', 'B', 'C']));
            assert($someLtree instanceof Ltree);
            self::assertSame(['A', 'B', 'C'], $someLtree->toArray());

            $otherLtree = $conn->querySingleValue('SELECT %ltree', ['_', 'x']);
            assert($otherLtree instanceof Ltree);
            self::assertSame(['_', 'x'], $otherLtree->toArray());

            $joinedLtree = $conn->querySingleValue('SELECT %ltree || %s', ['_'], 'y');
            assert($joinedLtree instanceof Ltree);
            self::assertSame(['_', 'y'], $joinedLtree->toArray());
        } finally {
            $tx->rollback();
        }
    }
}

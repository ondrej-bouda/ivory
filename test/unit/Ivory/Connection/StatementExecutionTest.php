<?php
namespace Ivory\Connection;

use Ivory\Exception\StatementException;
use Ivory\Relation\QueryRelation;
use Ivory\Result\SqlState;

class StatementExecutionTest extends \Ivory\IvoryTestCase
{
    public function testQueryError()
    {
        $conn = $this->getIvoryConnection();
        $rel = new QueryRelation($conn, 'select log(-10)');
        try {
            $rel->value();
            $this->fail('Error expected');
        }
        catch (StatementException $e) {
            $this->assertSame(SqlState::INVALID_ARGUMENT_FOR_LOGARITHM, $e->getSqlStateCode());
            $this->assertSame('ERROR', $e->getSeverity());
        }
    }
}

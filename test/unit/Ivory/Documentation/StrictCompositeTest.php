<?php
declare(strict_types=1);
namespace Ivory\Documentation;

use Ivory\IvoryTestCase;

/**
 * This test shows type-strict composites in Ivory.
 */
class StrictCompositeTest extends IvoryTestCase
{
    public function testCustomStrictType()
    {
        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $conn->command('CREATE TYPE parse_error AS (file TEXT, line INT, message TEXT)');
            $conn->getTypeRegister()->registerType(new ParseErrorType('public', 'parse_error'));

            $parseError = new ParseError('bar.h', 24);

            $val = $conn->querySingleValue(
                'SELECT %parse_error',
                $parseError
            );
            self::assertInstanceOf(ParseError::class, $val);
            assert($val instanceof ParseError);

            self::assertSame('bar.h', $val->file);
            self::assertSame(24, $val->line);
            self::assertSame(null, $val->message);

            try {
                $val->undefProp;
                self::fail('Accessing an undefined attribute should have emitted a warning');
            } catch (\PHPUnit\Framework\Error\Warning $e) {
            }
        } finally {
            $tx->rollback();
        }
    }

    public function testMultiplePlaceholdersInFreshType()
    {
        $conn = $this->getIvoryConnection();
        $tx = $conn->startTransaction();
        try {
            $conn->command('CREATE TYPE parse_error AS (file TEXT, line INT, message TEXT)');
            $conn->getTypeRegister()->registerType(new ParseErrorType('public', 'parse_error'));

            $parseError1 = new ParseError('foo.c', 2, 'Unexpected token: `(`');
            $parseError2 = new ParseError('bar.h', 24);

            $least = $conn->querySingleValue(
                'SELECT LEAST(%parse_error, %parse_error)',
                $parseError1,
                $parseError2
            );
            self::assertInstanceOf(ParseError::class, $least);
            assert($least instanceof ParseError);

            self::assertSame('bar.h', $least->file);
            self::assertSame(24, $least->line);
            self::assertSame(null, $least->message);

            try {
                $least->undefProp;
                self::fail('Accessing an undefined attribute should have emitted a warning');
            } catch (\PHPUnit\Framework\Error\Warning $e) {
            }
        } finally {
            $tx->rollback();
        }
    }
}

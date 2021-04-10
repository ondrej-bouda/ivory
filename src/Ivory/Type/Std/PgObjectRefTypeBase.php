<?php
declare(strict_types=1);
namespace Ivory\Type\Std;

use Ivory\Lang\Sql\Types;
use Ivory\Type\TypeBase;
use Ivory\Value\PgObjectRefBase;
use Ivory\Value\PgObjectSignatureRefBase;
use Ivory\Value\PgTypeRef;

/**
 * A common base for PostgreSQL object identifier types.
 *
 * @see https://www.postgresql.org/docs/11/datatype-oid.html
 */
abstract class PgObjectRefTypeBase extends TypeBase
{
    /**
     * Parses a potentially schema-qualified PostgreSQL object identifier.
     *
     * @param string $refStr
     * @return string[] pair: (schema name, object name)
     */
    final protected function parseObjectRef(string $refStr): array
    {
        $offset = 0;
        $result = $this->parseObjectRefSubstr($refStr, $offset);
        if ($offset == strlen($refStr)) {
            return $result;
        } else {
            throw $this->invalidValueException($refStr);
        }
    }

    /**
     * Parses a potentially schema-qualified PostgreSQL object identifier out of a part of the input string.
     *
     * @param string $str
     * @param int $offset offset at which to start parsing in <tt>$refStr</tt>; gets set to the end of the match
     * @return string[] pair: (schema name, object name)
     */
    private function parseObjectRefSubstr(string $str, int &$offset): array
    {
        $re = '~\G
                (?:                                 # optional schema name:
                    (?:                             # either...
                        "((?:[^"]+|"")+)"           # quoted name
                        |                           # ...or...
                        ([^".(),]+)                 # plain name
                    )
                    \s*
                    \.
                    \s*
                ) ?
                (?:                                 # object name: either...
                    "((?:[^"]+|"")+)"               # quoted name
                    |                               # ...or...
                    ([^".(),]+)                     # plain name
                )
                ~ux';

        if (!preg_match($re, $str, $m, 0, $offset)) {
            throw new \InvalidArgumentException('$refStr');
        }

        $offset += strlen($m[0]);

        return [
            ($m[1] !== '' ? strtr($m[1], ['""' => '"']) : ($m[2] !== '' ? $m[2] : null)),
            ($m[3] !== '' ? strtr($m[3], ['""' => '"']) : $m[4]),
        ];
    }

    final protected function parseObjectSignature(string $signatureStr): array
    {
        $offset = 0;
        $result = $this->parseObjectRefSubstr($signatureStr, $offset);
        if (($signatureStr[$offset] ?? '') != '(') {
            throw $this->invalidValueException($signatureStr);
        }
        $offset++;
        if (!isset($signatureStr[$offset])) {
            throw $this->invalidValueException($signatureStr);
        }
        if ($signatureStr[$offset] != ')') {
            while (true) {
                [$argTypeSchema, $argTypeName] = $this->parseObjectRefSubstr($signatureStr, $offset);
                $result[] = PgTypeRef::fromQualifiedName($argTypeSchema, $argTypeName);
                if (($signatureStr[$offset] ?? '') == ')') {
                    break;
                }
                if (($signatureStr[$offset] ?? '') != ',') {
                    throw $this->invalidValueException($signatureStr);
                }
                $offset++;
                while (ctype_space($signatureStr[$offset] ?? '')) {
                    $offset++;
                }
            }
        }
        $offset++;
        if (strlen($signatureStr) != $offset) {
            throw $this->invalidValueException($signatureStr);
        }
        return $result;
    }

    final protected function serializeObjectRef(PgObjectRefBase $val, bool $strictType): string
    {
        $refStr = Types::serializeIdent($val->getName());
        if ($val->getSchemaName() !== null) {
            $refStr = Types::serializeIdent($val->getSchemaName()) . '.' . $refStr;
        }
        return $this->indicateType($strictType, Types::serializeString($refStr));
    }

    final protected function serializeObjectSignature(PgObjectSignatureRefBase $val, bool $strictType): string
    {
        $signature = Types::serializeIdent($val->getName());
        if ($val->getSchemaName() !== null) {
            $signature = Types::serializeIdent($val->getSchemaName() . '.' . $signature);
        }
        $signature .= '(';
        $isFirst = true;
        foreach ($val->getArgTypes() as $argType) {
            if (!$isFirst) {
                $signature .= ', ';
            }
            $schemaName = $argType->getSchemaName();
            if ($schemaName !== null) {
                $signature .= Types::serializeIdent($schemaName) . '.';
            }
            $signature .= Types::serializeIdent($argType->getName());
            $isFirst = false;
        }
        $signature .= ')';
        return $this->indicateType($strictType, Types::serializeString($signature));
    }
}

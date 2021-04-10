<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Reference to a PostgreSQL object by its signature.
 */
abstract class PgObjectSignatureRefBase extends PgObjectRefBase
{
    private $argTypes;

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function fromUnqualifiedName(string $name, PgTypeRef ...$argTypes): PgObjectRefBase
    {
        $ref = new static(null, $name);
        $ref->argTypes = $argTypes;
        return $ref;
    }

    /** @noinspection PhpMissingParentCallCommonInspection */
    public static function fromQualifiedName(?string $schemaName, string $name, PgTypeRef ...$argTypes): PgObjectRefBase
    {
        $ref = new static($schemaName, $name);
        $ref->argTypes = $argTypes;
        return $ref;
    }

    /**
     * @return PgTypeRef[] list of argument types
     */
    final public function getArgTypes(): array
    {
        return $this->argTypes;
    }
}

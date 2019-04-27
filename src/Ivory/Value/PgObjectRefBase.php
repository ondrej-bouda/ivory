<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Reference to a named PostgreSQL object.
 */
abstract class PgObjectRefBase
{
    private $schemaName;
    private $name;

    public static function fromUnqualifiedName(string $name)
    {
        return new static(null, $name);
    }

    public static function fromQualifiedName(?string $schemaName, string $name)
    {
        return new static($schemaName, $name);
    }

    final protected function __construct(?string $schemaName, string $name)
    {
        $this->schemaName = $schemaName;
        $this->name = $name;
    }

    final public function getSchemaName(): ?string
    {
        return $this->schemaName;
    }

    final public function getName(): string
    {
        return $this->name;
    }
}

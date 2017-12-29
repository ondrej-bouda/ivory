<?php
declare(strict_types=1);
namespace Ivory\Lang\SqlPattern;

/**
 * Placeholder used within an {@link SqlPattern}.
 *
 * Immutable.
 */
class SqlPatternPlaceholder
{
    private $offset;
    private $nameOrPosition;
    private $typeName;
    private $typeNameQuoted;
    private $schemaName;
    private $schemaNameQuoted;

    /**
     * @param int $offset byte offset where is the place in the parsed SQL to insert value for the placeholder
     * @param string|int $nameOrPosition name or zero-based position of the placeholders within the SQL pattern
     * @param string|null $typeName name of type specified for the placeholder, or <tt>null</tt> if no type is given
     * @param bool $typeNameQuoted whether the type name is quoted (which might make a difference - e.g., int vs. "int")
     * @param string|null $schemaName name of schema of the type specified for the placeholder, or <tt>null</tt> if no
     *                                  schema is given
     * @param bool $schemaNameQuoted whether the schema name is quoted
     */
    public function __construct(
        int $offset,
        $nameOrPosition,
        ?string $typeName = null,
        bool $typeNameQuoted = false,
        ?string $schemaName = null,
        bool $schemaNameQuoted = false
    ) {
        $this->offset = $offset;
        $this->nameOrPosition = $nameOrPosition;
        $this->typeName = $typeName;
        $this->typeNameQuoted = $typeNameQuoted;
        $this->schemaName = $schemaName;
        $this->schemaNameQuoted = $schemaNameQuoted;
    }

    /**
     * @return int $offset byte offset where is the place in the {@link SqlPattern::toSql() parsed SQL} to insert value
     *                       for the placeholder
     */
    public function getOffset(): int
    {
        return $this->offset;
    }

    /**
     * @return string|int $nameOrPosition name or zero-based position of the placeholders within the SQL pattern
     */
    public function getNameOrPosition()
    {
        return $this->nameOrPosition;
    }

    /**
     * @return string|null name of type specified for the placeholder, or <tt>null</tt> if no type is given
     */
    public function getTypeName(): ?string
    {
        return $this->typeName;
    }

    /**
     * @return bool whether the type name is quoted (which might make a difference - e.g., int vs. "int")
     */
    public function isTypeNameQuoted(): bool
    {
        return $this->typeNameQuoted;
    }

    /**
     * @return string|null name of schema of the type specified for the placeholder, or <tt>null</tt> if no schema is
     *                       given
     */
    public function getSchemaName(): ?string
    {
        return $this->schemaName;
    }

    /**
     * @return bool whether the schema name is quoted (which might make a difference - e.g., MySchema vs. "MySchema")
     */
    public function isSchemaNameQuoted(): bool
    {
        return $this->schemaNameQuoted;
    }
}

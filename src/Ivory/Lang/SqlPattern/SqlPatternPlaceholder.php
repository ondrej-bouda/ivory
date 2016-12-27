<?php
namespace Ivory\Lang\SqlPattern;

/**
 * Placeholder used within an {@link SqlPattern}.
 */
class SqlPatternPlaceholder
{
    private $offset;
    private $nameOrPosition;
    private $typeName;

    /**
     * @param int $offset byte offset where is the place in the parsed SQL to insert value for the placeholder
     * @param string|int $nameOrPosition name or zero-based position of the placeholders within the SQL pattern
     * @param string|null $typeName name of type specified for the placeholder, or <tt>null</tt> if no type is given
     */
    public function __construct(int $offset, $nameOrPosition, string $typeName = null)
    {
        $this->offset = $offset;
        $this->nameOrPosition = $nameOrPosition;
        $this->typeName = $typeName;
    }

    /**
     * @return int $offset byte offset where is the place in the {@link SqlPattern::getSql() parsed SQL} to insert
     *                       value for the placeholder
     */
    public function getOffset() : int
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
     * @return string|null $typeName name of type specified for the placeholder, or <tt>null</tt> if no type is given
     */
    public function getTypeName()
    {
        return $this->typeName;
    }
}

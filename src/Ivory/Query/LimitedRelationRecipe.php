<?php
namespace Ivory\Query;

use Ivory\Type\ITypeDictionary;

class LimitedRelationRecipe extends RelationRecipe implements IRelationRecipe
{
    private $relRecipe;
    private $limit;
    private $offset;

    /**
     * LimitedRelationRecipe constructor.
     *
     * @param IRelationRecipe $relRecipe recipe for relation to limit
     * @param int|null $limit maximal number of rows to return; <tt>null</tt> for unlimited number of rows
     * @param int $offset a non-negative offset - the number of original rows to skip
     */
    public function __construct(IRelationRecipe $relRecipe, $limit, int $offset = 0)
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException('$offset is negative');
        }

        $this->relRecipe = $relRecipe;
        $this->limit = ($limit !== null ? (int)$limit : null); // PHP 7.1: declare the type as ?int
        $this->offset = $offset;
    }

    public function toSql(ITypeDictionary $typeDictionary) : string
    {
        $relSql = $this->relRecipe->toSql($typeDictionary);
        return sprintf(
            "SELECT *\nFROM (\n%s\n) t%s%s",
            $relSql,
            ($this->limit !== null ? "\nLIMIT {$this->limit}" : ''),
            ($this->offset ? "\nOFFSET {$this->offset}" : '')
        );
    }
}

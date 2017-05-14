<?php
namespace Ivory\Query;

use Ivory\Lang\Sql\ISqlPredicate;
use Ivory\Lang\SqlPattern\SqlPattern;
use Ivory\Type\ITypeDictionary;

class ConstrainedRelationRecipe extends RelationRecipe implements IRelationRecipe
{
    private $relRecipe;
    private $cond;
    private $args;

    /**
     * @param IRelationRecipe $relRecipe recipe for relation to constrain
     * @param ISqlPredicate|SqlPattern|string $cond
     * @param array $args
     */
    public function __construct(IRelationRecipe $relRecipe, $cond, ...$args)
    {
        if ($cond instanceof ISqlPredicate && $args) {
            throw new \InvalidArgumentException('$args not supported for ' . ISqlPredicate::class . ' condition');
        }


        $this->relRecipe = $relRecipe;
        $this->cond = $cond;
        $this->args = $args;
    }

    public function toSql(ITypeDictionary $typeDictionary): string
    {
        $relSql = $this->relRecipe->toSql($typeDictionary);

        if ($this->cond instanceof ISqlPredicate) {
            $condSql = $this->cond->getSql();
        } else {
            $condRecipe = SqlRelationRecipe::fromPattern($this->cond, ...$this->args);
            $condSql = $condRecipe->toSql($typeDictionary);
        }

        return "SELECT *\nFROM (\n$relSql\n) t\nWHERE $condSql";
    }
}

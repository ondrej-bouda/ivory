<?php
namespace Ivory\Query;

use Ivory\Lang\Sql\ISqlPredicate;
use Ivory\Lang\SqlPattern\SqlPattern;
use Ivory\Type\ITypeDictionary;

class ConstrainedRelationDefinition extends RelationDefinition implements IRelationDefinition
{
    private $baseRelDef;
    private $cond;
    private $args;

    /**
     * @param IRelationDefinition $relationDefinition definition of relation to constrain
     * @param ISqlPredicate|SqlPattern|string $cond
     * @param array $args
     */
    public function __construct(IRelationDefinition $relationDefinition, $cond, ...$args)
    {
        if ($cond instanceof ISqlPredicate && $args) {
            throw new \InvalidArgumentException('$args not supported for ' . ISqlPredicate::class . ' condition');
        }


        $this->baseRelDef = $relationDefinition;
        $this->cond = $cond;
        $this->args = $args;
    }

    public function toSql(ITypeDictionary $typeDictionary): string
    {
        $relSql = $this->baseRelDef->toSql($typeDictionary);

        if ($this->cond instanceof ISqlPredicate) {
            $condSql = $this->cond->getSql();
        } else {
            $condDef = SqlRelationDefinition::fromPattern($this->cond, ...$this->args);
            $condSql = $condDef->toSql($typeDictionary);
        }

        return "SELECT *\nFROM (\n$relSql\n) t\nWHERE $condSql";
    }
}

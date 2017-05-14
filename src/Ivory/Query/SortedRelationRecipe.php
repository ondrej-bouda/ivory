<?php
namespace Ivory\Query;

use Ivory\Lang\Sql\ISqlSortExpression;
use Ivory\Lang\SqlPattern\SqlPattern;
use Ivory\Type\ITypeDictionary;

class SortedRelationRecipe extends RelationRecipe implements IRelationRecipe
{
    private $relRecipe;
    private $sortExpr;
    private $args;

    /**
     * @param IRelationRecipe $relRecipe recipe for relation to constrain
     * @param ISqlSortExpression|SqlPattern|string|array $sortExpr
     * @param array $args
     */
    public function __construct(IRelationRecipe $relRecipe, $sortExpr, ...$args)
    {
        if ($args) {
            if (is_array($sortExpr)) {
                throw new \InvalidArgumentException('$args not supported for array of sort expressions');
            } elseif ($sortExpr instanceof ISqlSortExpression) {
                throw new \InvalidArgumentException(
                    '$args not supported for ' . ISqlSortExpression::class . ' sort expression'
                );
            }
        }

        $this->relRecipe = $relRecipe;
        $this->sortExpr = $sortExpr;
        $this->args = $args;
    }

    public function toSql(ITypeDictionary $typeDictionary): string
    {
        $relSql = $this->relRecipe->toSql($typeDictionary);

        $sortSql = self::getSortSql($typeDictionary, $this->sortExpr, ...$this->args);
        if ($sortSql !== null) {
            return "SELECT *\nFROM (\n$relSql\n) t\nORDER BY $sortSql";
        } else {
            return $relSql;
        }
    }

    private static function getSortSql(ITypeDictionary $typeDictionary, $expr, ...$args) // PHP 7.1: declare returning ?string
    {
        if ($expr instanceof ISqlSortExpression) {
            $sortSql = $expr->getExpression()->getSql();
            switch ($expr->getDirection()) {
                case ISqlSortExpression::ASC:
                    break;
                case ISqlSortExpression::DESC:
                    $sortSql .= ' DESC';
                    break;
                default:
                    throw new \LogicException("Unexpected direction of sort expression: {$expr->getDirection()}");
            }
            return $sortSql;
        } elseif (is_array($expr)) {
            $sqlParts = [];
            foreach ($expr as $sortSpec) {
                if (is_array($sortSpec)) {
                    $sqlPart = self::getSortSql($typeDictionary, ...$sortSpec);
                } else {
                    $sqlPart = self::getSortSql($typeDictionary, $sortSpec);
                }
                if ($sqlPart !== null) {
                    $sqlParts[] = $sqlPart;
                }
            }
            return ($sqlParts ? implode(', ', $sqlParts) : null);
        } else {
            $condRecipe = SqlRelationRecipe::fromPattern($expr, ...$args);
            return $condRecipe->toSql($typeDictionary);
        }
    }
}

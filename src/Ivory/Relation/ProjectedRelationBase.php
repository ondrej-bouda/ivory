<?php
declare(strict_types=1);
namespace Ivory\Relation;

abstract class ProjectedRelationBase extends StreamlinedRelation
{
    /** @var Column[] */
    private $projectedColumns;
    /** @var array map: column name => offset of the first column of the name, or {@link Tuple::AMBIGUOUS_COL} */
    private $projectedColNameMap;

    public function __construct(IRelation $source, array $columns)
    {
        parent::__construct($source);
        $this->projectedColumns = $columns;

        $this->projectedColNameMap = [];
        foreach ($columns as $colOffset => $col) {
            $colName = $col->getName();
            if ($colName !== null && $colName !== '') {
                $this->projectedColNameMap[$colName] = (isset($this->projectedColNameMap[$colName]) ?
                    Tuple::AMBIGUOUS_COL :
                    $colOffset
                );
            }
        }
    }


    /**
     * Converts a simple macro, as accepted, e.g., by {@link IRelation::project()}, to a PCRE.
     *
     * @param string $macroPattern the simple macro to convert
     * @param int $starCnt number of star wildcards is stored here
     * @return string PCRE equivalent to <tt>$macroPattern</tt>
     */
    protected static function simpleMacroPatternToPcre(string $macroPattern, int &$starCnt = null): string
    {
        $starCnt = 0;
        $pcre = '/^';
        $escaped = false;
        $lastLiteral = '';
        for ($i = 0; $i < strlen($macroPattern); $i++) {
            $c = $macroPattern[$i];
            if ($escaped) {
                $lastLiteral .= $c;
                $escaped = false;
            } else {
                switch ($c) {
                    case '\\':
                        $escaped = true;
                        break;
                    case '*':
                        $pcre .= preg_quote($lastLiteral, '/');
                        $lastLiteral = '';
                        $pcre .= '(.*)';
                        $starCnt++;
                        break;
                    default:
                        $lastLiteral .= $c;
                }
            }
        }
        if ($escaped) {
            $lastLiteral .= '\\';
        }
        $pcre .= preg_quote($lastLiteral, '/');
        $pcre .= '$/';
        return $pcre;
    }

    protected static function simpleMacroReplacementToPcre(string $macroReplacement): string
    {
        $repl = '';
        $stars = 0;
        $escaped = false;
        for ($i = 0; $i < strlen($macroReplacement); $i++) {
            $c = $macroReplacement[$i];
            if ($escaped) {
                if ($c == '$' || $c == '\\') {
                    $repl .= '\\';
                }
                $repl .= $c;
                $escaped = false;
            } else {
                switch ($c) {
                    case '\\':
                        $escaped = true;
                        break;
                    case '*':
                        $stars++;
                        $repl .= '${' . $stars . '}';
                        break;
                    /** @noinspection PhpMissingBreakStatementInspection */
                    case '$':
                        $repl .= '\\';
                        // no break
                    default:
                        $repl .= $c;
                }
            }
        }
        if ($escaped) {
            $repl .= '\\\\';
        }

        return $repl;
    }


    public function getColumns(): array
    {
        return $this->projectedColumns;
    }

    protected function getColNameMap(): array
    {
        return $this->projectedColNameMap;
    }

    public function col($offsetOrNameOrEvaluator): IColumn
    {
        return $this->_colImpl($offsetOrNameOrEvaluator, $this->projectedColumns, $this->projectedColNameMap, $this);
    }

    public function getIterator()
    {
        $cnt = $this->count();
        for ($i = 0; $i < $cnt; $i++) {
            yield $this->tuple($i);
        }
    }
}

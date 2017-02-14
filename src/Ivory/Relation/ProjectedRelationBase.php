<?php
namespace Ivory\Relation;

abstract class ProjectedRelationBase extends StreamlinedRelation
{
    /** @var Column[] */
    private $projectedColumns;
    /** @var int[] map: column name => offset of the first column of the name */
    private $colNameMap;

    public function __construct(IRelation $source, array $columns)
    {
        parent::__construct($source);
        $this->projectedColumns = $columns;

        $this->colNameMap = [];
        foreach ($columns as $colOffset => $col) {
            $colName = $col->getName();
            if (strlen($colName) > 0 && !isset($this->colNameMap[$colName])) {
                $this->colNameMap[$colName] = $colOffset;
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
    protected static function simpleMacroPatternToPcre($macroPattern, &$starCnt = null)
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

    protected static function simpleMacroReplacementToPcre($macroReplacement)
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


    public function getColumns()
    {
        return $this->projectedColumns;
    }

    protected function getColNameMap()
    {
        return $this->colNameMap;
    }

    public function populate()
    {
        throw new \Ivory\Exception\NotImplementedException(); // TODO
    }

    public function flush()
    {
    }

    public function col($offsetOrNameOrEvaluator): IColumn
    {
        return $this->_colImpl($offsetOrNameOrEvaluator, $this->projectedColumns, $this->colNameMap, $this);
    }

    public function getIterator()
    {
        return new RelationTupleIterator($this);
    }
}

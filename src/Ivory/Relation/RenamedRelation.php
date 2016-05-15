<?php
namespace Ivory\Relation;

class RenamedRelation extends StreamlinedRelation
{
    /** @var Column[] */
    private $columns;
    /** @var int[] map: column name => offset of the first column of the name */
    private $colNameMap;


    public function __construct($source, $renamePairs)
    {
        parent::__construct($source);
        $this->computeColumns($renamePairs);
    }

    private function computeColumns($renamePairs)
    {
        /** @var string[] $pcres list of PCREs for renaming columns*/
        $pcres = [];
        /** @var string[] $repls list of replacements for the corresponding PCREs */
        $repls = [];
        /** @var string $byOffset map: column offset => new name for the corresponding column */
        $byOffset = [];
        foreach ($renamePairs as $orig => $new) {
            if ($orig[0] == '/') {
                $pcres[] = $orig;
                $repls[] = $new;
            }
            elseif (is_int($orig) || filter_var((string)$orig, FILTER_VALIDATE_INT)) {
                $byOffset[$orig] = $new;
            }
            else {
                $pcre = '/^';
                $escaped = false;
                $lastLiteral = '';
                for ($i = 0; $i < strlen($orig); $i++) {
                    $c = $orig[$i];
                    if ($escaped) {
                        $lastLiteral .= $c;
                        $escaped = false;
                    }
                    else {
                        switch ($c) {
                            case '\\':
                                $escaped = true;
                                break;
                            case '*':
                                $pcre .= preg_quote($lastLiteral, '/');
                                $lastLiteral = '';
                                $pcre .= '(.*)';
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
                $pcres[] = $pcre;

                $repl = '';
                $stars = 0;
                $escaped = false;
                for ($i = 0; $i < strlen($new); $i++) {
                    $c = $new[$i];
                    if ($escaped) {
                        if ($c == '$' || $c == '\\') {
                            $repl .= '\\';
                        }
                        $repl .= $c;
                        $escaped = false;
                    }
                    else {
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
                            default:
                                $repl .= $c;
                        }
                    }
                }
                if ($escaped) {
                    $repl .= '\\\\';
                }
                $repls[] = $repl;
            }
        }

        $this->columns = [];
        $this->colNameMap = [];
        foreach ($this->source->getColumns() as $colOffset => $col) {
            $origName = $col->getName();
            if (isset($byOffset[$colOffset])) {
                $newName = $byOffset[$colOffset];
            }
            else {
                $newName = $origName;
                foreach ($pcres as $i => $pcre) {
                    $newName = preg_replace($pcre, $repls[$i], (string)$origName, -1, $replaced);
                    if ($replaced) {
                        break;
                    }
                }
            }
            if ($origName != $newName) {
                $col = $col->renameTo($newName);
            }
            $this->columns[] = $col;
            if (strlen($newName) > 0 && !isset($this->colNameMap[$newName])) {
                $this->colNameMap[$newName] = $colOffset;
            }
        }
    }

    public function getColumns()
    {
        return $this->columns;
    }

    public function populate()
    {
        throw new \Ivory\Exception\NotImplementedException(); // TODO
    }

    public function flush()
    {
    }

    public function col($offsetOrNameOrEvaluator)
    {
        return $this->_colImpl($offsetOrNameOrEvaluator, $this->columns, $this->colNameMap, $this);
    }

    public function tuple($offset = 0)
    {
        $tuple = parent::tuple($offset);
        return new Tuple($tuple->toList(), $this->getColumns(), $this->colNameMap);
    }

    public function getIterator()
    {
        return new RenamedRelationIterator(parent::getIterator(), $this->columns, $this->colNameMap);
    }
}

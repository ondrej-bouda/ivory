<?php
declare(strict_types=1);
namespace Ivory\Relation;

class RenamedRelation extends ProjectedRelationBase
{
    public function __construct(IRelation $source, iterable $renamePairs)
    {
        parent::__construct($source, self::defineColumns($source, $renamePairs));
    }

    private static function defineColumns(IRelation $source, iterable $renamePairs): array
    {
        /** @var string[] $pcres list of PCREs for renaming columns */
        $pcres = [];
        /** @var string[] $repls list of replacements for the corresponding PCREs */
        $repls = [];
        /** @var string $byOffset map: column offset => new name for the corresponding column */
        $byOffset = [];
        foreach ($renamePairs as $orig => $new) {
            if (is_string($orig) && $orig[0] == '/') {
                $pcres[] = $orig;
                $repls[] = $new;
            } elseif (is_int($orig) || filter_var((string)$orig, FILTER_VALIDATE_INT)) {
                $byOffset[$orig] = $new;
            } else {
                $pcres[] = self::simpleMacroPatternToPcre($orig);
                $repls[] = self::simpleMacroReplacementToPcre($new);
            }
        }

        $columns = [];
        foreach ($source->getColumns() as $colOffset => $col) {
            $origName = $col->getName();
            if (isset($byOffset[$colOffset])) {
                $newName = $byOffset[$colOffset];
            } else {
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
            $columns[] = $col;
        }

        return $columns;
    }

    public function tuple(int $offset = 0): ITuple
    {
        $tuple = parent::tuple($offset);
        return new Tuple($tuple->toList(), $this->getColNameMap());
    }
}

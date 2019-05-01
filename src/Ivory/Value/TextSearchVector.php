<?php
declare(strict_types=1);
namespace Ivory\Value;

/**
 * Text-search vector of lexemes.
 *
 * The vector is sorted by the lexemes.
 *
 * Optionally, each lexeme may have its position specified, which is a positive integer specifying where the lexeme
 * occurred in the original document. Lexemes with their position specified may further be labeled with a weight, which
 * can be either `A`, `B`, `C`, or `D`, the last being the default weight when unspecified.
 *
 * The objects are immutable.
 *
 * @see https://www.postgresql.org/docs/11/datatype-textsearch.html#DATATYPE-TSVECTOR
 */
class TextSearchVector
{
    const DEFAULT_WEIGHT = 'D';

    /** @var int[][][] sorted map: lexeme => list of (position, weight) pairs, or <tt>null</tt> if position is
     *                   unspecified;
     *                 sorted by the lexeme;
     *                 the positions are 1-based */
    private $lexemes;


    /**
     * @param string[] $lexemeSet array of lexemes
     * @return TextSearchVector vector containing each lexeme once, without positions
     */
    public static function fromSet(array $lexemeSet): TextSearchVector
    {
        $lexemes = array_fill_keys($lexemeSet, null);
        ksort($lexemes);
        return new TextSearchVector($lexemes);
    }

    /**
     * @param string[] $lexemeList array of lexemes
     * @return TextSearchVector vector containing each lexeme with each of its positions
     */
    public static function fromList(array $lexemeList): TextSearchVector
    {
        $lexemes = array_fill_keys($lexemeList, []);
        $pos = 1;
        foreach ($lexemeList as $lex) {
            $lexemes[$lex][] = [$pos, self::DEFAULT_WEIGHT];
            $pos++;
        }
        ksort($lexemes);
        return new TextSearchVector($lexemes);
    }

    /**
     * @param int[][][] $lexemes map: lexeme => list of (position, weight) pairs, or <tt>null</tt> if position is
     *                             unspecified;
     *                           the positions are 1-based
     * @return TextSearchVector
     */
    public static function fromMap(array $lexemes): TextSearchVector
    {
        ksort($lexemes);
        return new TextSearchVector($lexemes);
    }

    /**
     * @param string $str
     * @return TextSearchVector vector containing each lexeme in the given string once, without positions
     */
    public static function fromString(string $str): TextSearchVector
    {
        $tokens = self::tokenize($str);
        return self::fromSet($tokens);
    }

    /**
     * @param string $str
     * @return TextSearchVector vector containing each lexeme with each of its positions in the given string
     */
    public static function fromOrderedString(string $str): TextSearchVector
    {
        $tokens = self::tokenize($str);
        return self::fromList($tokens);
    }

    private static function tokenize(string $str): array
    {
        preg_match_all('~[^\s\']+|\'(?:[^\']+|\'\')*\'~', $str, $matches);
        $tokens = [];
        foreach ($matches[0] as $m) {
            if ($m[0] != "'") {
                $tokens[] = $m;
            } elseif (strlen($m) > 2) { // ignore just two single quotes not containing anything
                $tokens[] = strtr(substr($m, 1, -1), ["''" => "'"]); // cut off the quotes, cut down doubled quotes
            }
        }
        return $tokens;
    }


    private function __construct(array $lexemes)
    {
        $this->lexemes = $lexemes;
    }

    /**
     * @return int[][][] sorted map: lexeme => list of (position, weight) pairs, or <tt>null</tt> if position is
     *                     unspecified;
     *                   sorted by the lexeme;
     *                   the positions are 1-based
     */
    public function getLexemes(): array
    {
        return $this->lexemes;
    }
}

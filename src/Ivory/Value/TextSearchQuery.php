<?php
namespace Ivory\Value;

/**
 * Text-search query.
 *
 * The objects are immutable.
 *
 * @see http://www.postgresql.org/docs/9.4/static/datatype-textsearch.html#DATATYPE-TSQUERY
 */
class TextSearchQuery
{
    private $queryString;

    /**
     * @param string $queryString the complete query string, as to be given to PostgreSQL
     * @return TextSearchQuery
     */
    public static function fromString($queryString)
    {
        return new TextSearchQuery($queryString);
    }

    /**
     * @param string $format an {@link sprintf()}-like format string;
     *                       instead of each placeholder, a lexeme from <tt>$lexemes</tt> is used - quoted automatically
     *                         and escaped properly
     * @param string[] ...$lexemes lexemes to put instead of the format string placeholders
     * @return TextSearchQuery
     */
    public static function fromFormat($format, ...$lexemes)
    {
        $quoted = [];
        foreach ($lexemes as $lex) {
            $quoted[] = preg_replace('~.*[\s\':].*~', "'\\0'", strtr($lex, ["'" => "''"]));
        }
        $queryString = vsprintf($format, $quoted);
        return new TextSearchQuery($queryString);
    }

    private function __construct($queryString)
    {
        $this->queryString = $queryString;
    }

    public function toString()
    {
        return $this->queryString;
    }
}

<?php
declare(strict_types=1);

namespace Ivory\Lang\SqlPattern;

use Ivory\Cache\ICacheControl;

class CachingSqlPatternParser implements ISqlPatternParser
{
    const CACHE_KEY = 'SqlPattern';

    private $cacheControl;
    private $parser;

    public function __construct(ICacheControl $cacheControl)
    {
        $this->cacheControl = $cacheControl;
        $this->parser = new SqlPatternParser();
    }

    public function parse(string $sqlPatternString): SqlPattern
    {
        $cacheKey = $this->computeCacheKey($sqlPatternString);
        $pattern = $this->cacheControl->getCached($cacheKey);
        if ($pattern === null) {
            $pattern = $this->parser->parse($sqlPatternString);
            $this->cacheControl->cachePermanently($cacheKey, $pattern);
        }
        return $pattern;
    }

    private function computeCacheKey(string $sqlPattern): string
    {
        return self::CACHE_KEY . '.' . md5($sqlPattern);
    }
}

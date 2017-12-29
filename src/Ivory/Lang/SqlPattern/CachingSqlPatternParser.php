<?php
declare(strict_types=1);
namespace Ivory\Lang\SqlPattern;

use Ivory\Cache\ICacheControl;
use Psr\Cache\CacheException;

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
        try {
            $pattern = $this->cacheControl->getCached($cacheKey);
            if ($pattern === null) {
                $pattern = $this->parser->parse($sqlPatternString);
                $this->cacheControl->cachePermanently($cacheKey, $pattern);
            }
            return $pattern;
        } catch (CacheException $e) {
            trigger_error(
                'Error working with cache for SQL pattern. ' .
                'Parsing the SQL pattern with a non-caching parser, the performance is degraded. ' .
                'Exception message: ' . $e->getMessage(),
                E_USER_WARNING
            );
            return $this->parser->parse($sqlPatternString);
        }
    }

    private function computeCacheKey(string $sqlPattern): string
    {
        return self::CACHE_KEY . '.' . md5($sqlPattern);
    }
}

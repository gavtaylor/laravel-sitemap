<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap;

use DateTimeImmutable;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Throwable;

/**
 * Caches the result of a route scan so the route table is only walked
 * once per cache window, not once per request to either sitemap.
 */
final class SitemapCache
{
    private const string CACHE_KEY = 'sitemap.urls';

    public function __construct(
        private readonly RouteScanner $scanner,
        private readonly CacheRepository $cache,
    ) {
        //
    }

    /**
     * @return list<SitemapUrl>
     */
    public function get(): array
    {
        $ttl = (int) config('sitemap.cache_seconds', 3600);

        if ($ttl <= 0) {
            return $this->scanner->scan();
        }

        $cached = $this->read();

        if ($cached !== null) {
            return $this->hydrate($cached);
        }

        $urls = $this->scanner->scan();

        $this->write($urls, $ttl);

        return $urls;
    }

    public function clear(): void
    {
        try {
            $this->cache->forget(self::CACHE_KEY);
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @return list<array{url: string, group: string, lastmod: string|null}>|null
     */
    private function read(): ?array
    {
        try {
            /** @var list<array{url: string, group: string, lastmod: string|null}>|null $cached */
            $cached = $this->cache->get(self::CACHE_KEY);

            return $cached;
        } catch (Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * @param  list<SitemapUrl>  $urls
     */
    private function write(array $urls, int $ttl): void
    {
        try {
            $this->cache->put(
                self::CACHE_KEY,
                array_map(fn (SitemapUrl $url) => $url->toArray(), $urls),
                $ttl,
            );
        } catch (Throwable $e) {
            report($e);
        }
    }

    /**
     * @param  list<array{url: string, group: string, lastmod: string|null}>  $cached
     * @return list<SitemapUrl>
     */
    private function hydrate(array $cached): array
    {
        try {
            return array_map(
                fn (array $url) => new SitemapUrl(
                    $url['url'],
                    $url['group'],
                    $url['lastmod'] !== null ? new DateTimeImmutable($url['lastmod']) : null,
                ),
                $cached,
            );
        } catch (Throwable $e) {
            report($e);

            return $this->scanner->scan();
        }
    }
}

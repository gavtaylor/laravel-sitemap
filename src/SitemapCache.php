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
     * Every scanned URL, or just one sitemap's URLs when $sitemap is given.
     * Callers that reuse the scan as their own URL list (IndexNow) should
     * keep calling this with no argument: splitting a named sitemap off the
     * HTML page does not drop those URLs from the full list.
     *
     * @return list<SitemapUrl>
     */
    public function get(?string $sitemap = null): array
    {
        $urls = $this->all();

        if ($sitemap === null) {
            return $urls;
        }

        return array_values(array_filter(
            $urls,
            fn (SitemapUrl $url): bool => $url->sitemap === $sitemap,
        ));
    }

    /**
     * @return list<SitemapUrl>
     */
    private function all(): array
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
     * @return list<array{url: string, group: string, label: string, lastmod: string|null, sitemap?: string}>|null
     */
    private function read(): ?array
    {
        try {
            /** @var list<array{url: string, group: string, label: string, lastmod: string|null, sitemap?: string}>|null $cached */
            $cached = $this->cache->get(self::CACHE_KEY);

            // A payload written before named sitemaps has no `sitemap` key.
            // Treat it as a miss so the next request files each URL correctly
            // instead of leaving an archive on the default sitemap until the TTL.
            if (is_array($cached) && isset($cached[0]) && ! array_key_exists('sitemap', $cached[0])) {
                return null;
            }

            return is_array($cached) ? $cached : null;
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
     * @param  list<array{url: string, group: string, label: string, lastmod: string|null, sitemap?: string}>  $cached
     * @return list<SitemapUrl>
     */
    private function hydrate(array $cached): array
    {
        try {
            return array_map(
                fn (array $url) => new SitemapUrl(
                    $url['url'],
                    $url['group'],
                    $url['label'],
                    $url['lastmod'] !== null ? new DateTimeImmutable($url['lastmod']) : null,
                    $url['sitemap'] ?? 'default',
                ),
                $cached,
            );
        } catch (Throwable $e) {
            report($e);

            return $this->scanner->scan();
        }
    }
}

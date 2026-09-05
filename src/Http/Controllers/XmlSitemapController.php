<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap\Http\Controllers;

use GavTaylor\Sitemap\SitemapCache;
use GavTaylor\Sitemap\SitemapUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the sitemaps.org-protocol XML sitemap. Splits into a
 * <sitemapindex> of numbered chunks once the URL count exceeds the
 * configured chunk size, matching Google's 50,000 URL per-file limit.
 */
final class XmlSitemapController
{
    public function __construct(
        private readonly SitemapCache $cache,
    ) {
        //
    }

    public function __invoke(Request $request): Response
    {
        $urls = collect($this->cache->get())->sortBy(fn (SitemapUrl $url) => $url->url)->values();

        $chunkSize = max(1, (int) config('sitemap.chunk_size', 50000));

        if ($urls->count() <= $chunkSize) {
            return $this->urlsetResponse($urls);
        }

        $page = $request->integer('page');

        if ($page >= 1) {
            return $this->urlsetResponse($urls->slice(($page - 1) * $chunkSize, $chunkSize)->values());
        }

        return $this->indexResponse((int) ceil($urls->count() / $chunkSize));
    }

    /**
     * @param  Collection<int, SitemapUrl>  $urls
     */
    private function urlsetResponse(Collection $urls): Response
    {
        return response(
            View::make('sitemap::xml', ['urls' => $urls])->render(),
            200,
            ['Content-Type' => 'application/xml'],
        );
    }

    private function indexResponse(int $pages): Response
    {
        $baseUrl = url((string) config('sitemap.xml_path', '/sitemap.xml'));

        $sitemaps = collect(range(1, $pages))->map(fn (int $page) => $baseUrl.'?page='.$page);

        return response(
            View::make('sitemap::xml-index', ['sitemaps' => $sitemaps])->render(),
            200,
            ['Content-Type' => 'application/xml'],
        );
    }
}

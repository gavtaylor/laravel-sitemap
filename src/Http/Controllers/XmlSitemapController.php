<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap\Http\Controllers;

use GavTaylor\Sitemap\SitemapCache;
use GavTaylor\Sitemap\Sitemaps;
use GavTaylor\Sitemap\SitemapUrl;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Serves the sitemaps.org-protocol XML sitemap. With no named sitemaps
 * configured, this is a flat <urlset>, or a <sitemapindex> of numbered
 * chunks once the URL count exceeds the configured chunk size.
 *
 * With named sitemaps configured, the main XML path is always a
 * <sitemapindex> linking the default page list and each named sitemap.
 * A child that itself exceeds the chunk size is listed as numbered
 * ?page= files, never as a nested index. The protocol only allows an
 * index to point at urlsets.
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
        $requested = $request->route('sitemap');
        $requested = is_string($requested) && $requested !== '' ? $requested : null;

        $named = Sitemaps::named();

        if ($requested === null && $named !== []) {
            return $this->indexResponse($this->indexLocs($named));
        }

        $sitemap = $requested ?? 'default';

        $urls = collect($this->cache->get($sitemap))->sortBy(fn (SitemapUrl $url): string => $url->url)->values();

        $chunkSize = $this->chunkSize();
        $page = $request->integer('page');

        if ($page >= 1) {
            return $this->urlsetResponse($urls->slice(($page - 1) * $chunkSize, $chunkSize)->values());
        }

        if ($urls->count() <= $chunkSize) {
            return $this->urlsetResponse($urls);
        }

        $baseUrl = url($this->xmlPathFor($sitemap, $named));

        return $this->indexResponse(array_map(
            fn (int $page): string => $this->chunkUrl($baseUrl, $page),
            range(1, (int) ceil($urls->count() / $chunkSize)),
        ));
    }

    /**
     * @param  array<string, array{xml_path: string, route_names: list<string>}>  $named
     * @return list<string>
     */
    private function indexLocs(array $named): array
    {
        $locs = $this->locsFor('default', (string) config('sitemap.pages_xml_path', '/sitemap-pages.xml'));

        foreach ($named as $name => $definition) {
            array_push($locs, ...$this->locsFor($name, $definition['xml_path']));
        }

        return $locs;
    }

    /**
     * A sitemap at or under the chunk size is one URL. Over it, the index
     * lists each page's urlset directly so an index never points at another index.
     *
     * @return list<string>
     */
    private function locsFor(string $sitemap, string $path): array
    {
        $count = count($this->cache->get($sitemap));
        $chunkSize = $this->chunkSize();

        if ($count <= $chunkSize) {
            return [url($path)];
        }

        $pages = (int) ceil($count / $chunkSize);

        return array_map(
            fn (int $page): string => $this->chunkUrl(url($path), $page),
            range(1, $pages),
        );
    }

    private function chunkUrl(string $baseUrl, int $page): string
    {
        return $baseUrl.'?page='.$page;
    }

    /**
     * @param  array<string, array{xml_path: string, route_names: list<string>}>  $named
     */
    private function xmlPathFor(string $sitemap, array $named): string
    {
        if ($sitemap === 'default') {
            if ($named === []) {
                return (string) config('sitemap.xml_path', '/sitemap.xml');
            }

            return (string) config('sitemap.pages_xml_path', '/sitemap-pages.xml');
        }

        return $named[$sitemap]['xml_path'] ?? (string) config('sitemap.xml_path', '/sitemap.xml');
    }

    private function chunkSize(): int
    {
        return max(1, (int) config('sitemap.chunk_size', 50000));
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

    /**
     * @param  list<string>  $sitemaps
     */
    private function indexResponse(array $sitemaps): Response
    {
        return response(
            View::make('sitemap::xml-index', ['sitemaps' => $sitemaps])->render(),
            200,
            ['Content-Type' => 'application/xml'],
        );
    }
}

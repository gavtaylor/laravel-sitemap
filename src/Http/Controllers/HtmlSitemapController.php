<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap\Http\Controllers;

use GavTaylor\Sitemap\SitemapCache;
use GavTaylor\Sitemap\SitemapUrl;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

final class HtmlSitemapController
{
    public function __construct(
        private readonly SitemapCache $cache,
    ) {
        //
    }

    public function __invoke(): Response
    {
        $groups = collect($this->cache->get())
            ->sortBy(fn (SitemapUrl $url) => $url->url)
            ->groupBy(fn (SitemapUrl $url) => $url->group)
            ->sortKeys();

        return response(
            View::make('sitemap::html', ['groups' => $groups])->render(),
        );
    }
}

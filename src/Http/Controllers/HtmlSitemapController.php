<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap\Http\Controllers;

use GavTaylor\Sitemap\RouteScanner;
use GavTaylor\Sitemap\SitemapCache;
use GavTaylor\Sitemap\SitemapUrl;
use Illuminate\Support\Collection;
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
        $grouped = collect($this->cache->get())
            ->sortBy(fn (SitemapUrl $url) => $url->label)
            ->groupBy(fn (SitemapUrl $url) => $url->group);

        // A group of exactly one page isn't really a grouping - it's just
        // an isolated section for a single standalone page (e.g. /about,
        // /contact). Fold those into the homepage's general bucket instead,
        // and only give a segment its own section once it actually has more
        // than one page in it (e.g. /blog and its many posts).
        $keptGroups = $grouped->filter(
            fn (Collection $urls, string $group) => $group === RouteScanner::ROOT_GROUP || $urls->count() > 1,
        );

        $foldedUrls = $grouped->except($keptGroups->keys())->flatten(1);

        // Home belongs first within that bucket too, not wherever it falls
        // alphabetically among the pages folded in alongside it.
        $general = $keptGroups->get(RouteScanner::ROOT_GROUP, collect())
            ->merge($foldedUrls)
            ->sortBy(fn (SitemapUrl $url) => $url->label)
            ->sortByDesc(fn (SitemapUrl $url) => $url->group === RouteScanner::ROOT_GROUP)
            ->values();

        // The general bucket belongs first regardless of where it falls
        // alphabetically - a visitor expects "home" at the top of a list of
        // pages, not buried wherever its group name happens to sort. Group
        // labels are already display-ready (RouteScanner headlines the
        // automatic ones and leaves a custom sitemap_group metadata value
        // exactly as the app set it - e.g. "ECA Committee" must not be
        // re-headlined into "Eca Committee").
        $groups = collect([RouteScanner::ROOT_GROUP => $general])
            ->filter(fn (Collection $urls) => $urls->isNotEmpty())
            ->merge($keptGroups->except(RouteScanner::ROOT_GROUP)->sortKeys());

        return response(
            View::make('sitemap::html', ['groups' => $groups])->render(),
        );
    }
}

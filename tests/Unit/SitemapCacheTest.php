<?php

declare(strict_types=1);

use GavTaylor\Sitemap\SitemapCache;
use GavTaylor\Sitemap\SitemapUrl;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route as RouteFacade;

beforeEach(function () {
    RouteFacade::get('/cached-page', fn () => '')->name('cached-page');
});

it('caches the scan result', function () {
    expect(Cache::has('sitemap.urls'))->toBeFalse();

    app(SitemapCache::class)->get();

    expect(Cache::has('sitemap.urls'))->toBeTrue();
});

it('does not cache when the ttl is 0', function () {
    config(['sitemap.cache_seconds' => 0]);

    app(SitemapCache::class)->get();

    expect(Cache::has('sitemap.urls'))->toBeFalse();
});

it('clears the cached entry', function () {
    app(SitemapCache::class)->get();

    expect(Cache::has('sitemap.urls'))->toBeTrue();

    app(SitemapCache::class)->clear();

    expect(Cache::has('sitemap.urls'))->toBeFalse();
});

it('rescans when a cached payload has no sitemap key', function () {
    Cache::put('sitemap.urls', [[
        'url' => 'https://example.com/stale',
        'group' => 'General',
        'label' => 'Stale',
        'lastmod' => null,
    ]], 3600);

    $urls = collect(app(SitemapCache::class)->get())->pluck('url');

    expect($urls->all())->not->toContain('https://example.com/stale');
    expect($urls->contains(fn (string $url) => str_ends_with($url, '/cached-page')))->toBeTrue();
});

it('returns one named sitemap without dropping the others from the full list', function () {
    config(['sitemap.sitemaps' => [
        'news' => [
            'xml_path' => '/sitemap-news.xml',
            'route_names' => ['news.story'],
        ],
    ]]);

    RouteFacade::get('/about', fn () => '')->name('about');
    RouteFacade::get('/news/story', fn () => '')->name('news.story');

    $news = collect(app(SitemapCache::class)->get('news'))->map(fn (SitemapUrl $url) => $url->url);
    $all = collect(app(SitemapCache::class)->get())->map(fn (SitemapUrl $url) => $url->url);

    expect($news->all())->toContain(url('/news/story'))
        ->and($news->all())->not->toContain(url('/about'))
        ->and($all->all())->toContain(url('/about'), url('/news/story'));
});

it('falls back to a fresh scan when the cached entry is corrupted', function () {
    Cache::put('sitemap.urls', 'not-a-valid-payload', 3600);

    $urls = app(SitemapCache::class)->get();

    expect($urls)->toBeArray()->not->toBeEmpty();
});

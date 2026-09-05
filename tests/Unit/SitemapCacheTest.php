<?php

declare(strict_types=1);

use GavTaylor\Sitemap\SitemapCache;
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

it('falls back to a fresh scan when the cached entry is corrupted', function () {
    Cache::put('sitemap.urls', 'not-a-valid-payload', 3600);

    $urls = app(SitemapCache::class)->get();

    expect($urls)->toBeArray()->not->toBeEmpty();
});

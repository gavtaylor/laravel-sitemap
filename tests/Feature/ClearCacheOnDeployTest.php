<?php

declare(strict_types=1);

use GavTaylor\Sitemap\SitemapCache;
use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\Cache;

uses(WithConsoleEvents::class);

it('clears the sitemap cache after a configured command finishes successfully', function () {
    app(SitemapCache::class)->get();

    expect(Cache::has('sitemap.urls'))->toBeTrue();

    $this->artisan('migrate', ['--force' => true]);

    expect(Cache::has('sitemap.urls'))->toBeFalse();
});

it('does not clear the cache after a command that is not configured', function () {
    app(SitemapCache::class)->get();

    expect(Cache::has('sitemap.urls'))->toBeTrue();

    $this->artisan('list');

    expect(Cache::has('sitemap.urls'))->toBeTrue();
});

it('does not clear the cache for any command when disabled', function () {
    config(['sitemap.clear_cache_after_commands' => []]);

    app(SitemapCache::class)->get();

    $this->artisan('migrate', ['--force' => true]);

    expect(Cache::has('sitemap.urls'))->toBeTrue();
});

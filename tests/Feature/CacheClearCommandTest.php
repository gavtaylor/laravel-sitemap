<?php

declare(strict_types=1);

use GavTaylor\Sitemap\SitemapCache;
use Illuminate\Support\Facades\Cache;

it('clears the sitemap cache', function () {
    app(SitemapCache::class)->get();

    expect(Cache::has('sitemap.urls'))->toBeTrue();

    $this->artisan('sitemap:clear')
        ->expectsOutputToContain('Sitemap cache cleared.')
        ->assertSuccessful();

    expect(Cache::has('sitemap.urls'))->toBeFalse();
});

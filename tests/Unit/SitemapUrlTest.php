<?php

declare(strict_types=1);

use GavTaylor\Sitemap\SitemapUrl;

it('converts to an array with an ISO 8601 lastmod', function () {
    $url = new SitemapUrl('https://example.com/about', 'general', 'About', new DateTimeImmutable('2026-01-01T12:00:00+00:00'));

    expect($url->toArray())->toBe([
        'url' => 'https://example.com/about',
        'group' => 'general',
        'label' => 'About',
        'lastmod' => '2026-01-01T12:00:00+00:00',
    ]);
});

it('converts to an array with a null lastmod', function () {
    $url = new SitemapUrl('https://example.com/about', 'general', 'About');

    expect($url->toArray())->toBe([
        'url' => 'https://example.com/about',
        'group' => 'general',
        'label' => 'About',
        'lastmod' => null,
    ]);
});

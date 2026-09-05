<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route as RouteFacade;

it('serves the xml sitemap using the sitemaps.org namespace', function () {
    $response = $this->get(config('sitemap.xml_path'))->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('application/xml');

    $response->assertSee('http://www.sitemaps.org/schemas/sitemap/0.9', false);
});

it('includes a loc for a registered route', function () {
    RouteFacade::get('/about', fn () => '')->name('about');

    $this->get(config('sitemap.xml_path'))
        ->assertOk()
        ->assertSee('<loc>'.url('/about').'</loc>', false);
});

it('serves a sitemap index once the url count exceeds the chunk size', function () {
    config(['sitemap.chunk_size' => 1]);

    RouteFacade::get('/one', fn () => '')->name('one');
    RouteFacade::get('/two', fn () => '')->name('two');

    $this->get(config('sitemap.xml_path'))
        ->assertOk()
        ->assertSee('<sitemapindex', false);
});

it('serves a chunked page via the page query parameter', function () {
    config(['sitemap.chunk_size' => 1]);

    RouteFacade::get('/one', fn () => '')->name('one');
    RouteFacade::get('/two', fn () => '')->name('two');

    $this->get(config('sitemap.xml_path').'?page=1')
        ->assertOk()
        ->assertSee('<urlset', false);
});

<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

beforeEach(function () {
    // An isolated public path per test, not the shared Testbench public/
    // directory - parallel test workers would otherwise race on the same
    // real robots.txt file on disk.
    $this->publicPath = sys_get_temp_dir().'/laravel-sitemap-robots-'.uniqid();
    File::makeDirectory($this->publicPath, recursive: true);
    $this->app->usePublicPath($this->publicPath);
});

afterEach(function () {
    File::deleteDirectory($this->publicPath);
});

it('creates robots.txt with a Sitemap line when the file does not exist', function () {
    $this->artisan('sitemap:link-robots')
        ->expectsOutputToContain('Added Sitemap:')
        ->assertSuccessful();

    expect(file_get_contents(public_path('robots.txt')))
        ->toContain('Sitemap: '.url(config('sitemap.xml_path')));
});

it('appends a Sitemap line when robots.txt exists without one', function () {
    file_put_contents(public_path('robots.txt'), "User-agent: *\nDisallow:\n");

    $this->artisan('sitemap:link-robots')->assertSuccessful();

    $contents = file_get_contents(public_path('robots.txt'));

    expect($contents)->toContain('Disallow:')
        ->toContain('Sitemap: '.url(config('sitemap.xml_path')));
});

it('does nothing when robots.txt already references the sitemap', function () {
    $url = url(config('sitemap.xml_path'));
    file_put_contents(public_path('robots.txt'), "User-agent: *\nSitemap: {$url}\n");

    $this->artisan('sitemap:link-robots')
        ->expectsOutputToContain('already references')
        ->assertSuccessful();

    $contents = file_get_contents(public_path('robots.txt'));

    expect(substr_count($contents, 'Sitemap:'))->toBe(1);
});

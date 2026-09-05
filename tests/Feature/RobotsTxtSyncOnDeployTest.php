<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\WithConsoleEvents;
use Illuminate\Support\Facades\File;

uses(WithConsoleEvents::class);

beforeEach(function () {
    // An isolated public path per test, not the shared Testbench public/
    // directory - parallel test workers would otherwise race on the same
    // real robots.txt file on disk.
    $this->publicPath = sys_get_temp_dir().'/laravel-sitemap-robots-deploy-'.uniqid();
    File::makeDirectory($this->publicPath, recursive: true);
    $this->app->usePublicPath($this->publicPath);
});

afterEach(function () {
    File::deleteDirectory($this->publicPath);
});

it('creates robots.txt after a configured command finishes successfully', function () {
    expect(is_file(public_path('robots.txt')))->toBeFalse();

    $this->artisan('migrate', ['--force' => true]);

    $contents = file_get_contents(public_path('robots.txt'));

    expect($contents)->toContain('Sitemap: '.url(config('sitemap.xml_path')));
});

it('does not sync robots.txt after a command that is not configured', function () {
    $this->artisan('list');

    expect(is_file(public_path('robots.txt')))->toBeFalse();
});

it('does not sync robots.txt for any command when disabled', function () {
    config(['sitemap.sync_robots_after_commands' => []]);

    $this->artisan('migrate', ['--force' => true]);

    expect(is_file(public_path('robots.txt')))->toBeFalse();
});

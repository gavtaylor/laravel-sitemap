<?php

declare(strict_types=1);

use GavTaylor\Sitemap\Support\RobotsTxtWarning;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;

beforeEach(function () {
    $this->robotsPath = sys_get_temp_dir().'/laravel-sitemap-robots-warning-'.uniqid().'/robots.txt';
    File::makeDirectory(dirname($this->robotsPath), recursive: true);
});

afterEach(function () {
    File::deleteDirectory(dirname($this->robotsPath));
});

it('does nothing when robots.txt does not exist', function () {
    Log::shouldReceive('warning')->never();

    (new RobotsTxtWarning($this->robotsPath, 'https://example.com/sitemap.xml'))->check();
});

it('does nothing when robots.txt already references the sitemap', function () {
    file_put_contents($this->robotsPath, "User-agent: *\nSitemap: https://example.com/sitemap.xml\n");

    Log::shouldReceive('warning')->never();

    (new RobotsTxtWarning($this->robotsPath, 'https://example.com/sitemap.xml'))->check();
});

it('warns when robots.txt exists without a matching sitemap line', function () {
    file_put_contents($this->robotsPath, "User-agent: *\nDisallow:\n");

    Log::shouldReceive('warning')
        ->once()
        ->with(Mockery::pattern('/sitemap:link-robots/'));

    (new RobotsTxtWarning($this->robotsPath, 'https://example.com/sitemap.xml'))->check();
});

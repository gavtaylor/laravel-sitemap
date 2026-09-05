<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap\Tests;

use GavTaylor\Sitemap\SitemapServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            SitemapServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('cache.default', 'array');
        $app['config']->set('app.maintenance.driver', 'cache');
        $app['config']->set('app.maintenance.store', 'array');
    }
}

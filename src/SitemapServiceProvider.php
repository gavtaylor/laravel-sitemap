<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap;

use GavTaylor\Sitemap\Console\Commands\ClearSitemapCacheCommand;
use GavTaylor\Sitemap\Http\Controllers\HtmlSitemapController;
use GavTaylor\Sitemap\Http\Controllers\XmlSitemapController;
use GavTaylor\Sitemap\Support\RouteCollisionWarning;
use Illuminate\Console\Events\CommandFinished;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

final class SitemapServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/sitemap.php', 'sitemap');
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'sitemap');

        $this->registerRoutes();

        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../config/sitemap.php' => config_path('sitemap.php'),
        ], ['sitemap', 'sitemap-config']);

        $this->publishes([
            __DIR__.'/../resources/views' => $this->app->resourcePath('views/vendor/sitemap'),
        ], ['sitemap', 'sitemap-views']);

        $this->commands([
            ClearSitemapCacheCommand::class,
        ]);

        $this->registerCacheClearOnDeploy();
    }

    /**
     * Laravel has no single "deployment finished" event, but almost every
     * deploy runs `artisan migrate` - so that's the default trigger used to
     * clear a now-stale route scan without requiring a manual step or a
     * cron-based TTL guess. Configurable/disableable via
     * `sitemap.clear_cache_after_commands`.
     */
    private function registerCacheClearOnDeploy(): void
    {
        $this->app->make(Dispatcher::class)->listen(CommandFinished::class, function (CommandFinished $event): void {
            /** @var list<string> $commands */
            $commands = config('sitemap.clear_cache_after_commands', []);

            if ($event->exitCode === 0 && in_array($event->command, $commands, true)) {
                $this->app->make(SitemapCache::class)->clear();
            }
        });
    }

    private function registerRoutes(): void
    {
        if (! config('sitemap.enabled', true)) {
            return;
        }

        $htmlPath = (string) config('sitemap.path', '/sitemap');
        $xmlPath = (string) config('sitemap.xml_path', '/sitemap.xml');
        $namePrefix = (string) config('sitemap.route_name_prefix', 'sitemap');

        (new RouteCollisionWarning($this->app->make('router'), $htmlPath))->check();
        (new RouteCollisionWarning($this->app->make('router'), $xmlPath))->check();

        $middleware = array_values(array_filter((array) config('sitemap.middleware', [])));

        Route::get($htmlPath, HtmlSitemapController::class)
            ->middleware($middleware)
            ->name("{$namePrefix}.html");

        Route::get($xmlPath, XmlSitemapController::class)
            ->middleware($middleware)
            ->name("{$namePrefix}.xml");

        PreventRequestsDuringMaintenance::except($htmlPath);
        PreventRequestsDuringMaintenance::except($xmlPath);
    }
}

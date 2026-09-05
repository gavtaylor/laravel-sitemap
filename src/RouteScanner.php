<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap;

use Closure;
use DateTimeInterface;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionFunction;

/**
 * Scans the application's registered routes and returns the subset that
 * are safe to publish in a sitemap: GET-able, fully resolvable without a
 * route parameter, not gated behind authentication, and not owned by a
 * vendor package (including this one).
 */
final class RouteScanner
{
    public function __construct(
        private readonly Router $router,
        private readonly Container $container,
    ) {
        //
    }

    /**
     * @return list<SitemapUrl>
     */
    public function scan(): array
    {
        $urls = [];

        foreach ($this->router->getRoutes()->getRoutes() as $route) {
            if (! $this->isIncluded($route)) {
                continue;
            }

            $urls[] = new SitemapUrl(
                url: (string) url($route->uri()),
                group: $this->group($route->uri()),
                lastmod: $this->lastmod($route),
            );
        }

        return $urls;
    }

    private function isIncluded(Route $route): bool
    {
        if (! in_array('GET', $route->methods(), true)) {
            return false;
        }

        if ($route->parameterNames() !== []) {
            return false;
        }

        if ($this->isRedirect($route)) {
            return false;
        }

        if ($this->isOwnRoute($route)) {
            return false;
        }

        if ($this->hasExcludedMiddleware($route)) {
            return false;
        }

        if ($this->isExcludedByName($route) || $this->isExcludedByPath($route)) {
            return false;
        }

        return ! $this->isVendorRoute($route);
    }

    /**
     * A sitemap should never send a search engine to a URL that immediately
     * 3xx's it somewhere else - list the destination, not the redirect.
     * Route::redirect()/Route::permanentRedirect() both register
     * \Illuminate\Routing\RedirectController, which is deliberately *not*
     * treated as a vendor route below (so an app's own Route::view() /
     * Route::redirect() calls aren't excluded as if they were framework
     * internals) - so this needs its own, separate check.
     */
    private function isRedirect(Route $route): bool
    {
        return $route->getControllerClass() === '\Illuminate\Routing\RedirectController';
    }

    private function isOwnRoute(Route $route): bool
    {
        $prefix = (string) config('sitemap.route_name_prefix', 'sitemap');

        return Str::is("{$prefix}.*", (string) $route->getName());
    }

    private function hasExcludedMiddleware(Route $route): bool
    {
        /** @var list<string> $excluded */
        $excluded = config('sitemap.excluded_middleware', []);

        if ($excluded === []) {
            return false;
        }

        foreach ($route->gatherMiddleware() as $middleware) {
            if (Str::is($excluded, $middleware)) {
                return true;
            }
        }

        return false;
    }

    private function isExcludedByName(Route $route): bool
    {
        /** @var list<string> $patterns */
        $patterns = config('sitemap.excluded_route_names', []);

        return $patterns !== [] && $route->getName() !== null && Str::is($patterns, $route->getName());
    }

    private function isExcludedByPath(Route $route): bool
    {
        /** @var list<string> $patterns */
        $patterns = config('sitemap.excluded_paths', []);

        return $patterns !== [] && Str::is($patterns, $route->uri());
    }

    /**
     * Mirrors Illuminate\Foundation\Console\RouteListCommand::isVendorRoute()
     * so package/framework routes (Horizon, Telescope, Debugbar, and this
     * package's own routes if they were ever renamed away from the default
     * name prefix) are excluded the same way `route:list --except-vendor` does.
     */
    private function isVendorRoute(Route $route): bool
    {
        $uses = $route->getAction('uses');

        if ($uses instanceof Closure) {
            $path = (new ReflectionFunction($uses))->getFileName();
        } elseif (is_string($uses) && str_contains($uses, 'SerializableClosure')) {
            return false;
        } elseif (is_string($uses)) {
            if (in_array($route->getControllerClass(), [
                '\Illuminate\Routing\RedirectController',
                '\Illuminate\Routing\ViewController',
            ], true)) {
                return false;
            }

            $controllerClass = $route->getControllerClass();

            if ($controllerClass === null || ! class_exists($controllerClass)) {
                return false;
            }

            $path = (new ReflectionClass($controllerClass))->getFileName();
        } else {
            return false;
        }

        return $path !== false && str_starts_with($path, base_path('vendor'));
    }

    private function group(string $uri): string
    {
        $segment = explode('/', trim($uri, '/'))[0];

        return $segment === '' ? 'general' : $segment;
    }

    private function lastmod(Route $route): ?DateTimeInterface
    {
        $resolver = config('sitemap.lastmod_resolver');

        if ($resolver === null || $resolver === '') {
            return null;
        }

        $result = $this->container->call($resolver, ['route' => $route]);

        return $result instanceof DateTimeInterface ? $result : null;
    }
}

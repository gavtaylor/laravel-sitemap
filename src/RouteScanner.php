<?php

declare(strict_types=1);

namespace GavTaylor\Sitemap;

use Closure;
use DateTimeImmutable;
use DateTimeInterface;
use Exception;
use Illuminate\Contracts\Container\Container;
use Illuminate\Routing\Route;
use Illuminate\Routing\Router;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionFunction;

/**
 * Scans the application's registered routes and returns the subset that
 * are safe to publish in a sitemap: GET-able, not gated behind
 * authentication, and not owned by a vendor package (including this one).
 * A route with a required {parameter} is included only if the app has
 * registered a resolver for it (see resolveParameterized()); otherwise it's
 * excluded, since there's no way to know what concrete URLs it represents.
 */
final class RouteScanner
{
    /**
     * The group a route with no URI segments (the homepage) is placed in.
     */
    public const string ROOT_GROUP = 'general';

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

            if ($route->parameterNames() !== []) {
                array_push($urls, ...$this->resolveParameterized($route));

                continue;
            }

            $url = (string) url($route->uri());

            $urls[] = new SitemapUrl(
                url: $url,
                group: $this->group($route->uri()),
                label: $this->label($route),
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

        if ($this->isRedirect($route)) {
            return false;
        }

        if ($this->hasFragment($route)) {
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
     * A route with a {parameter} has no single URL of its own - it's
     * excluded entirely unless the app has registered a resolver for it in
     * `sitemap.route_resolvers` (keyed by route name, so the route must be
     * named to be resolvable). The resolver is called once per scan and
     * yields one entry per concrete URL the route actually represents (e.g.
     * every published blog post for a `/blog/{slug}` route).
     *
     * @return list<SitemapUrl>
     */
    private function resolveParameterized(Route $route): array
    {
        $name = $route->getName();

        if ($name === null || $name === '') {
            return [];
        }

        /** @var array<string, callable|string> $resolvers */
        $resolvers = config('sitemap.route_resolvers', []);
        $resolver = $resolvers[$name] ?? null;

        if ($resolver === null) {
            return [];
        }

        $items = $this->container->call($resolver, ['route' => $route]);

        $urls = [];

        foreach ($items as $item) {
            $resolved = $this->normalizeResolvedItem($item, $route);

            $url = route($name, $resolved['parameters']);

            $urls[] = new SitemapUrl(
                url: $url,
                group: $this->group((string) parse_url($url, PHP_URL_PATH)),
                label: $resolved['label'] ?? $this->labelFromSegment((string) parse_url($url, PHP_URL_PATH)),
                lastmod: $this->normalizeLastmod($resolved['lastmod']),
            );
        }

        return $urls;
    }

    /**
     * @return array{parameters: array<string, mixed>, label: string|null, lastmod: mixed}
     */
    private function normalizeResolvedItem(mixed $item, Route $route): array
    {
        if (is_array($item) && array_key_exists('parameters', $item)) {
            return [
                'parameters' => (array) $item['parameters'],
                'label' => isset($item['label']) ? (string) $item['label'] : null,
                'lastmod' => $item['lastmod'] ?? null,
            ];
        }

        $firstParameter = $route->parameterNames()[0] ?? null;

        return [
            'parameters' => $firstParameter !== null ? [$firstParameter => $item] : [],
            'label' => null,
            'lastmod' => null,
        ];
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

    /**
     * A browser never sends a URL's fragment (the part after `#`) to the
     * server - so a route registered with one in its URI (e.g. to give an
     * in-page anchor its own named route) can never actually be requested,
     * and would otherwise show up as a spurious, non-canonical duplicate of
     * its own base URL in the sitemap.
     */
    private function hasFragment(Route $route): bool
    {
        return str_contains($route->uri(), '#');
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

        return $segment === '' ? self::ROOT_GROUP : $segment;
    }

    /**
     * A human-readable label for the HTML sitemap, since a raw URL makes a
     * poor link's visible text. Prefers the route name (curated by the
     * developer, e.g. `clients.index` -> "Clients") and falls back to the
     * last URI segment (e.g. an unnamed `/about-us` -> "About Us") when the
     * route has no name.
     */
    private function label(Route $route): string
    {
        $name = $route->getName();

        if ($name !== null && $name !== '') {
            return $this->dropIndexSuffix(Str::headline(str_replace(['.', '_'], ' ', $name)));
        }

        return $this->labelFromSegment($route->uri());
    }

    /**
     * A route named by Laravel's resource-controller convention (e.g.
     * `clients.index`) headlines to "Clients Index" - "Index" is developer
     * jargon for "the listing page", not a word a visitor would use, and
     * that listing page is usually *the* page for its section anyway, so
     * the plain resource name alone reads better: "Clients".
     */
    private function dropIndexSuffix(string $headline): string
    {
        return Str::endsWith($headline, ' Index') ? Str::before($headline, ' Index') : $headline;
    }

    private function labelFromSegment(string $uri): string
    {
        $segment = collect(explode('/', trim($uri, '/')))->last();

        return $segment !== null && $segment !== '' ? Str::headline($segment) : 'Home';
    }

    private function lastmod(Route $route): ?DateTimeInterface
    {
        $resolver = config('sitemap.lastmod_resolver');

        if ($resolver === null || $resolver === '') {
            return null;
        }

        return $this->normalizeLastmod($this->container->call($resolver, ['route' => $route]));
    }

    private function normalizeLastmod(mixed $value): ?DateTimeInterface
    {
        if ($value instanceof DateTimeInterface) {
            return $value;
        }

        if (is_string($value) && $value !== '') {
            try {
                return new DateTimeImmutable($value);
            } catch (Exception) {
                return null;
            }
        }

        return null;
    }
}

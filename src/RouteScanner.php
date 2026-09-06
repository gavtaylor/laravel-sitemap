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
    public const string ROOT_GROUP = 'General';

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
            $group = $this->group($route);

            $urls[] = new SitemapUrl(
                url: $url,
                group: $group,
                label: $this->stripRedundantGroupPrefix($this->label($route), $group),
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

            $uri = $this->substituteParameters($route->uri(), $resolved['parameters']);
            $url = (string) url($uri);
            $group = $this->group($route);

            $urls[] = new SitemapUrl(
                url: $url,
                group: $group,
                label: $resolved['label'] ?? $this->stripRedundantGroupPrefix($this->labelFromSegment($uri), $group),
                lastmod: $this->normalizeLastmod($resolved['lastmod']),
            );
        }

        return $urls;
    }

    /**
     * Substitutes resolved values into the route's own URI pattern directly,
     * rather than looking the route up again by name via the global route()
     * helper - Illuminate\Routing\RouteCollection only indexes a route by
     * name once *something* has forced a refresh of its name lookup table
     * (normally triggered by dispatching a real HTTP request), which a named
     * route registered earlier in the very same scan may not have had happen
     * yet. Substituting directly needs no such lookup: the concrete Route
     * instance is already in hand from the scan itself.
     *
     * @param  array<string, mixed>  $parameters
     */
    private function substituteParameters(string $uri, array $parameters): string
    {
        foreach ($parameters as $name => $value) {
            $uri = preg_replace(
                '/\{'.preg_quote((string) $name, '/').'\??\}/',
                rawurlencode((string) $value),
                $uri,
                1,
            ) ?? $uri;
        }

        return $uri;
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

    /**
     * Two-tier fallback: the route's own name prefix (Laravel's own
     * `Route::name('about-us.')->group(...)` convention) if it has one,
     * else the URL's first segment.
     */
    private function group(Route $route): string
    {
        return $this->namePrefixGroup($route) ?? $this->urlSegmentGroup($route);
    }

    /**
     * Laravel's own convention for organising related route names -
     * `Route::name('about-us.')->group(fn () => ...)` - without touching
     * URLs at all. A route named `about-us.index` groups under "About Us"
     * alongside `about-us.plan`, even though their URIs (`/about-us`,
     * `/six-point-plan`) share no path segment in common. Only used when
     * the name actually has a prefix; a flat name (no dot) falls through
     * to the URL segment below exactly as before.
     */
    private function namePrefixGroup(Route $route): ?string
    {
        $name = $route->getName();

        if ($name === null || ! str_contains($name, '.')) {
            return null;
        }

        $prefix = Str::before($name, '.');

        return $prefix === '' ? null : $this->headline($prefix);
    }

    private function urlSegmentGroup(Route $route): string
    {
        $segment = explode('/', trim($route->uri(), '/'))[0];

        return $segment === '' ? self::ROOT_GROUP : $this->headline($segment);
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
            return $this->dropIndexSuffix($this->headline(str_replace(['.', '_'], ' ', $name)));
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

        return $segment !== null && $segment !== '' ? $this->headline($segment) : 'Home';
    }

    /**
     * Str::headline(), then applies the configured word-level casing
     * overrides (see sitemap.label_glossary config docs) - so an acronym
     * that Str::headline() would otherwise flatten to title case (e.g.
     * "eca-committee" -> "Eca Committee") reads correctly ("ECA Committee")
     * in every label and group heading it appears in, from one glossary
     * entry rather than a full-label override per affected route.
     */
    private function headline(string $value): string
    {
        $headline = Str::headline($value);

        /** @var array<string, string> $glossary */
        $glossary = config('sitemap.label_glossary', []);

        if ($glossary === []) {
            return $headline;
        }

        $lookup = [];

        foreach ($glossary as $word => $replacement) {
            $lookup[Str::lower((string) $word)] = $replacement;
        }

        $words = array_map(
            fn (string $word) => $lookup[Str::lower($word)] ?? $word,
            explode(' ', $headline),
        );

        return implode(' ', $words);
    }

    /**
     * A route named for the sake of grouping (e.g. `about-us.plan`, grouped
     * under "About Us" via its name prefix) would otherwise get a label of
     * "About Us Plan" - correct by the same rules that turn `clients.index`
     * into "Clients", but redundant once "About Us" is already showing as
     * the group heading above it. Strips exactly that leading repeat, never
     * a resolver's own explicit label (never passed through this method).
     */
    private function stripRedundantGroupPrefix(string $label, string $group): string
    {
        return Str::startsWith($label, "{$group} ") ? Str::after($label, "{$group} ") : $label;
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

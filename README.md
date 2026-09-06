# Laravel Sitemap

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gavtaylor/laravel-sitemap.svg?style=flat-square)](https://packagist.org/packages/gavtaylor/laravel-sitemap)
[![tests](https://github.com/gavtaylor/laravel-sitemap/actions/workflows/tests.yml/badge.svg)](https://github.com/gavtaylor/laravel-sitemap/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/gavtaylor/laravel-sitemap.svg?style=flat-square)](https://packagist.org/packages/gavtaylor/laravel-sitemap)

A sitemap for Laravel that scans your application's own registered routes instead of maintaining a separate sitemap definition - an HTML version for human visitors, and an XML version for search engines.

There is no separate list of URLs to keep in sync. This package walks `Route::getRoutes()`, keeps the routes that are safe to publish (`GET`-able, not behind authentication, not owned by a vendor package - a `{parameter}` route is included too, if you tell it how to enumerate one), and caches the result so the route table is only scanned once per cache window - not once per request.

## Installation

```bash
composer require gavtaylor/laravel-sitemap
```

The package auto-registers itself and serves `/sitemap` (HTML) and `/sitemap.xml` (XML) immediately - no other setup is required.

## The HTML view

Override the default view with zero config change:

```bash
php artisan vendor:publish --tag=sitemap-views
```

This copies the views into `resources/views/vendor/sitemap/`, where Laravel's own view resolution already looks before falling back to the package's copy. Edit `html.blade.php` directly to extend your site's own layout - the package can't safely guess your theme, so this is how "matches the site's theme" is achieved.

The HTML view receives a single variable:

| Variable  | Type                                          | Description                                                    |
|-----------|------------------------------------------------|------------------------------------------------------------------|
| `$groups` | `Collection<string, Collection<int, SitemapUrl>>` | Included URLs, grouped (see [Grouping](#grouping) below) and sorted alphabetically by label both within and across groups. A group with only one page (e.g. `/about`) is folded into "General" alongside the homepage instead of getting a one-item section of its own; "General" always comes first, with the homepage pinned at the top of it |

`SitemapUrl` is a simple read-only object: `$url->url` (string), `$url->group` (string, the raw un-headlined segment), `$url->label` (string, human-readable link text - see below), `$url->lastmod` (`?DateTimeInterface`).

This is a public contract: once you've customised the view, treat changes to these variables as breaking changes.

### Labels

A raw URL makes a poor link's visible text, so every `SitemapUrl` carries a human-readable `label` too - the package's own bundled view uses it, and a custom one should too. It's derived from the route name where possible (`clients.index` -> "Clients" - the trailing "Index" from Laravel's resource-controller convention is dropped, since it reads as developer jargon rather than something a visitor would say), falling back to the last URI segment for an unnamed route (`/about-us` -> "About Us"). A [route resolver](#resolving-parameterized-routes) can supply an exact label per URL instead, when the humanised guess isn't good enough (e.g. a blog post's real title).

Labels (and [group headings](#grouping)) are title-cased with `Str::headline()`, which has no idea an acronym should stay fully uppercase - `eca-committee` headlines to "Eca Committee". Rather than overriding the full label of every affected route by hand, list the correction once as a `label_glossary` entry:

```php
// config/sitemap.php
'label_glossary' => [
    'eca' => 'ECA',
    'reach newsletter' => 'REACH Newsletter',
],
```

A single-word key (`'eca'`) fixes every occurrence of that word, case-insensitively, in any label or group heading - "Eca Committee", "Eca 2026 Candidate", and so on - with nothing to maintain per-route as new ones are added. A space-separated phrase key (`'reach newsletter'`) matches only that exact run of words instead, for a word that shouldn't be corrected everywhere it appears - `'reach' => 'REACH'` alone would wrongly force an unrelated route's "Reach" into "REACH" too, so scope it to the specific phrase that actually means an acronym. Phrases are matched longest-first, so a multi-word entry always wins over a shorter one that only matches its first word.

The replacement is substituted verbatim, so a phrase entry can fix more than casing - `'six point' => 'Six-Point'` turns "Six Point Plan" into "Six-Point Plan".

## The XML view

`/sitemap.xml` follows the [sitemaps.org protocol](https://www.sitemaps.org/protocol.html) (the specification Google, Bing, and others actually implement - it predates and isn't itself an RFC). Each URL gets a `<loc>`, and a `<lastmod>` only if you've configured a resolver (see below). `<priority>` and `<changefreq>` are deliberately never emitted - Google's own documentation says both are ignored, so there's nothing to configure.

Once the number of included URLs exceeds `chunk_size` (default 50,000, matching the sitemaps.org/Google per-file limit), `/sitemap.xml` automatically serves a `<sitemapindex>` pointing at numbered pages (`?page=1`, `?page=2`, ...) instead of a flat `<urlset>`. Nothing to configure for this to kick in.

## Letting crawlers discover it

A crawler finds `sitemap.xml` passively via a `Sitemap:` line in `robots.txt` - not via an HTML `<link>` tag, and not automatically, since `robots.txt` is almost always served as a static file in `public/` (as Laravel's own default install ships one) that the web server returns directly, before Laravel ever sees the request. This package can't safely add a route for it (it would be silently shadowed by that static file), so it never touches `robots.txt` from a real web request. It does, however, keep it in sync automatically after a deploy:

```php
// config/sitemap.php
'sync_robots_after_commands' => ['migrate'], // the default; [] to disable
```

The same "a deploy probably just happened" signal used for [cache-clearing](#caching) also syncs `robots.txt`, since a console command runs as the same user that just wrote the rest of the deployed code (unlike a real HTTP request, where the web server user often can't write to `public/`):

- **Missing entirely** - created outright, allowing all crawling (`User-agent: *\nDisallow:`) plus the `Sitemap:` line.
- **Exists, no `Sitemap:` line** - the line is appended; everything else in the file is left untouched.
- **Exists, but its `Sitemap:` line points somewhere else** - left alone. A mismatch here usually means a stale entry from before a domain/path change, and that's a human decision this package won't guess at - a warning is logged instead (see below), not an error, since the app still loads fine either way.

Run `php artisan sitemap:link-robots` any time to apply the same logic immediately rather than waiting for the next deploy - useful right after installing the package, or to check current status. Safe to run more than once - it's a no-op once the line is present, and it warns (rather than silently overwriting) the same mismatch case above.

This has nothing to do with duplicate-content risk, in case that's a concern: having both an HTML sitemap (for people) and an XML sitemap (for crawlers) pointing at the same URLs isn't a penalty - Google's own guidance recommends exactly this pairing. An XML sitemap isn't indexed as a page in its own right; it's a protocol file, not content competing for ranking.

## Configuration

```bash
php artisan vendor:publish --tag=sitemap-config
```

See the generated `config/sitemap.php` for every option, documented inline. Highlights below.

### Grouping

The HTML sitemap groups pages into sections. Two-tier fallback, no config needed either way:

1. **The route's own name prefix**, if it has one - Laravel's native `Route::name('about-us.')->group(...)` convention, with no URL or existing-name changes required for routes that don't need it:

   ```php
   Route::name('about-us.')->group(function () {
       Route::get('/about-us', ...)->name('index');       // about-us.index
       Route::get('/six-point-plan', ...)->name('plan');  // about-us.plan
   });
   ```

   Both group under "About Us" (headlined from the `about-us` prefix) even though their URLs share no path segment. A route's label is derived the same way it always is (see [Labels](#labels)), but with a redundant leading repeat of the group name stripped - `about-us.plan` becomes "About Us Plan" as a label, then "About Us" is stripped since it's already the section heading, leaving just "Plan".

2. **The URL's first path segment**, for a route with a flat, unprefixed name (or none at all) - unchanged from a plain URL-segment scan: `/blog/latest` groups under "Blog" regardless of its name.

Reorganise existing routes into name-prefixed groups only where it's actually useful - a route that's already fine on its own doesn't need one.

Three independent, composable ways to keep a route out of both sitemaps:

```php
// config/sitemap.php
'excluded_middleware' => ['auth', 'auth:*', 'signed', 'password.confirm'], // the default
'excluded_route_names' => ['admin.*'],
'excluded_paths' => ['internal/*'],
```

`excluded_route_names` and `excluded_paths` are matched with `Str::is()`, so wildcards work. `Route::redirect()`/`Route::permanentRedirect()` routes are always excluded too - a sitemap should never send a crawler to a URL that immediately 3xx's it elsewhere; list the destination instead. A route whose URI contains a `#` fragment (e.g. `Route::get('/pricing#annual', ...)`, sometimes used to give an in-page anchor its own named route) is always excluded as well - a browser never sends the fragment to the server, so the route can never really be requested, and would otherwise show up as a spurious duplicate of its own base URL. `Route::view()` routes are included normally. Routes owned by a vendor package (Horizon, Telescope, Debugbar, this package's own routes, etc.) are excluded automatically, the same way `php artisan route:list --except-vendor` identifies them. A route with a `{parameter}` is excluded unless a [resolver](#resolving-parameterized-routes) is registered for it.

**Known limitation:** only *declarative* redirects (`Route::redirect()`/`Route::permanentRedirect()`) are detected. A closure or controller method that calls the `redirect()` helper itself at runtime looks identical to a normal page from the route table alone - this package only ever inspects route definitions, and deliberately never executes a route to find out what it actually returns (doing so could trigger real side effects). If a route like that needs to stay out of the sitemap, exclude it explicitly by name or path.

**`guest`-only pages are included by default.** A login/register/password-reset page has no `auth` middleware (it's the opposite - only reachable when signed *out*), so it isn't excluded by the default list. For an app that's entirely internal/private, add `'guest'` (or `'guest:*'`) to `excluded_middleware` if you don't want its auth-flow pages showing up in the sitemap.

### Resolving parameterized routes

A route like `/blog/{slug}` has no single URL of its own, so it's excluded by default - there's no way to know what concrete slugs exist just from the route table. Register a resolver, keyed by route name, to tell the package how to enumerate them:

```php
// config/sitemap.php
'route_resolvers' => [
    'blog.show' => \App\Sitemap\PostSlugs::class,
],
```

A resolver is a callable - a class-string of an invokable class, or a `'\Class@method'` string, not a `Closure`, so the config file stays safe to `config:cache` - that returns an iterable of the route's concrete values. For a route with a single parameter, yield the plain values:

```php
final class PostSlugs
{
    public function __invoke(): array
    {
        return array_keys(require resource_path('writing/posts.php'));
    }
}
```

That's enough for `/blog/{slug}` to expand into one sitemap entry per key, with a humanised label built from the slug (`hello-world` -> "Hello World") and no `<lastmod>`. For a route with more than one parameter, or to control the label/`<lastmod>` exactly (e.g. a post's real title and its actual last-updated date), yield an array instead:

```php
final class PostSlugs
{
    public function __invoke(): array
    {
        return collect(require resource_path('writing/posts.php'))
            ->map(fn (array $post, string $slug) => [
                'parameters' => ['slug' => $slug],
                'label' => $post['title'],
                'lastmod' => $post['updated_at'] ?? null,
            ])
            ->values()
            ->all();
    }
}
```

Only `parameters` is required in that form; omit `label`/`lastmod` to fall back to the humanised guess / no `<lastmod>` for that entry. The route must be named (resolvers are looked up by name), and the resolver receives the `Illuminate\Routing\Route` if it needs it (`public function __invoke(Route $route): array`).

### Caching

```php
'cache_seconds' => 3600, // 0 disables caching entirely
```

Routes rarely change outside of a deploy, so the scan result is cached rather than recomputed on every request. Clear it manually any time with the bundled command:

```bash
php artisan sitemap:clear
```

#### Clearing the cache on deploy

Laravel doesn't have a single "deployment finished" event to hook into, but almost every deploy runs `php artisan migrate` - so the cache is cleared automatically once that (or any command you list) finishes successfully, without you having to add a step to your deploy script:

```php
'clear_cache_after_commands' => ['migrate'], // the default; [] to disable
```

Add other commands your deploy already runs (e.g. `optimize`) if you'd rather key off one of those instead, or set it to `[]` and rely purely on `cache_seconds` and/or a manual `sitemap:clear`.

### Last modified dates

```php
'lastmod_resolver' => null, // an invokable class-string, e.g. \App\Sitemap\LastmodResolver::class
```

Left `null` by default. Google's own guidance is that an untrustworthy `lastmod` is worse than none, and this package has no way to know when a route's content last changed. If you do wire one up, it receives the `Illuminate\Routing\Route` and should return a `DateTimeInterface` (or `null` to omit `<lastmod>` for that URL) - only return a real value when you can vouch it reflects an actual content change, not a build/deploy timestamp.

## Testing

See [CONTRIBUTING.md](.github/CONTRIBUTING.md) for the full setup/lint/test workflow.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Issues and pull requests are welcome. As a native-focused Laravel package, code changes are held to [Laravel's own coding standards](https://laravel.com/framework/docs/contributions#coding-style) - see [CONTRIBUTING.md](.github/CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](.github/SECURITY.md) on how to report security vulnerabilities.

## Credits

- [Gavin Taylor](https://github.com/gavtaylor)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.

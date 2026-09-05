# Laravel Sitemap

[![Latest Version on Packagist](https://img.shields.io/packagist/v/gavtaylor/laravel-sitemap.svg?style=flat-square)](https://packagist.org/packages/gavtaylor/laravel-sitemap)
[![tests](https://github.com/gavtaylor/laravel-sitemap/actions/workflows/tests.yml/badge.svg)](https://github.com/gavtaylor/laravel-sitemap/actions/workflows/tests.yml)
[![Total Downloads](https://img.shields.io/packagist/dt/gavtaylor/laravel-sitemap.svg?style=flat-square)](https://packagist.org/packages/gavtaylor/laravel-sitemap)

A sitemap for Laravel that scans your application's own registered routes instead of maintaining a separate sitemap definition - an HTML version for human visitors, and an XML version for search engines.

There is no separate list of URLs to keep in sync. This package walks `Route::getRoutes()`, keeps the routes that are safe to publish (`GET`-able, no unresolved `{parameter}`, not behind authentication, not owned by a vendor package), and caches the result so the route table is only scanned once per cache window - not once per request.

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
| `$groups` | `Collection<string, Collection<int, SitemapUrl>>` | Included URLs, grouped by their first path segment and sorted alphabetically both within and across groups |

`SitemapUrl` is a simple read-only object: `$url->url` (string), `$url->group` (string), `$url->lastmod` (`?DateTimeInterface`).

This is a public contract: once you've customised the view, treat changes to these variables as breaking changes.

## The XML view

`/sitemap.xml` follows the [sitemaps.org protocol](https://www.sitemaps.org/protocol.html) (the specification Google, Bing, and others actually implement - it predates and isn't itself an RFC). Each URL gets a `<loc>`, and a `<lastmod>` only if you've configured a resolver (see below). `<priority>` and `<changefreq>` are deliberately never emitted - Google's own documentation says both are ignored, so there's nothing to configure.

Once the number of included URLs exceeds `chunk_size` (default 50,000, matching the sitemaps.org/Google per-file limit), `/sitemap.xml` automatically serves a `<sitemapindex>` pointing at numbered pages (`?page=1`, `?page=2`, ...) instead of a flat `<urlset>`. Nothing to configure for this to kick in.

## Configuration

```bash
php artisan vendor:publish --tag=sitemap-config
```

See the generated `config/sitemap.php` for every option, documented inline. Highlights below.

### Excluding routes

Three independent, composable ways to keep a route out of both sitemaps:

```php
// config/sitemap.php
'excluded_middleware' => ['auth', 'auth:*', 'signed', 'password.confirm'], // the default
'excluded_route_names' => ['admin.*'],
'excluded_paths' => ['internal/*'],
```

`excluded_route_names` and `excluded_paths` are matched with `Str::is()`, so wildcards work. Routes with any `{parameter}` at all, required or optional (e.g. `/posts/{post}`, `/archive/{year?}`), are always excluded - there is no per-route model-enumeration resolver in this package, by design. Routes owned by a vendor package (Horizon, Telescope, Debugbar, this package's own routes, etc.) are excluded automatically, the same way `php artisan route:list --except-vendor` identifies them.

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

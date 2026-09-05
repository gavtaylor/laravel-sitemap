---
name: laravel-sitemap-development
description: >
  Configure and apply the Laravel Sitemap package in Laravel applications.
license: MIT
metadata:
  author: Gavin Taylor
---

# Laravel Sitemap

Use this skill when a Laravel application needs to integrate the Laravel Sitemap package.

## Primary Goal

- apply the `gavtaylor/laravel-sitemap` package's public API in the smallest correct way

## Workflow

### 1. Inspect the Laravel app context

- confirm the app is a Laravel project
- check whether `config/sitemap.php` has been published (`php artisan vendor:publish --tag=sitemap-config`) before assuming any non-default config
- the package auto-registers two routes at boot: `GET /sitemap` (HTML, named `sitemap.html`) and `GET /sitemap.xml` (XML, named `sitemap.xml`) - both paths are configurable

### 2. Apply the package's public API

- **How it works**: the package scans `Route::getRoutes()` on every cache miss - there is no separate sitemap definition to maintain. Only `GET`/`HEAD` routes with no excluded middleware and no vendor-package origin are included. A route with a `{parameter}` is included only if a resolver is registered for it (see below); otherwise it's excluded. `Route::redirect()`/`Route::permanentRedirect()` routes are always excluded (never point a crawler at a 3xx); so are routes whose URI contains a `#` fragment (never reaches the server, so it's never a real distinct resource). `Route::view()` routes are included normally. A closure/controller that calls `redirect()` itself at runtime is *not* detected as a redirect - only the declarative `Route::redirect()` form is; exclude those explicitly by name/path if needed.
- **Custom HTML**: publish the view (`--tag=sitemap-views`) and edit `resources/views/vendor/sitemap/html.blade.php` directly to extend the app's own layout. It receives `$groups` (a `Collection<string, Collection<int, SitemapUrl>>`, alphabetically sorted by label). Never invent new variables here - treat that as a fixed contract. Use `$url->label` for link text, never `$url->url` - a raw URL is a poor link label.
- **Grouping**: a route's section is its own name prefix if it has one (`Route::name('about-us.')->group(...)` - Laravel's native convention, zero URL/naming changes for routes that don't need it), else its URL's first segment. To pull otherwise-unrelated URLs into one section (matching a nav menu, say), wrap them in a name-prefixed group rather than inventing a workaround - don't rename routes that are already fine standing alone just to force a grouping.
- **Excluding routes**: prefer `excluded_middleware` (matches by middleware name/alias, e.g. `auth`, `auth:*`) over enumerating route names one by one. Use `excluded_route_names` / `excluded_paths` (both support `Str::is()` wildcards) for anything middleware can't express.
- **Parameterized routes** (e.g. `/blog/{slug}`): register a resolver in `route_resolvers`, keyed by route name (the route must be named), pointing at an invokable class-string (never a `Closure` - it must survive `config:cache`) that returns an iterable of that route's concrete values. Plain scalars work for a single-parameter route and get a humanised label; yield `['parameters' => [...], 'label' => ..., 'lastmod' => ...]` instead for multiple parameters or an exact label/date (e.g. a real post title, not a humanised slug). No resolver means the route stays excluded - this is a real, common need (any content-listing route), not an edge case to wave off.
- **Cache**: the scanned route list is cached for `cache_seconds` (default 3600). It's also cleared automatically after `clear_cache_after_commands` (default `['migrate']`) finishes successfully - Laravel has no single "deployment finished" event, so this piggybacks on a command almost every deploy already runs. Add to that list, run `php artisan sitemap:clear` manually, or lower/zero `cache_seconds` if the app's deploy doesn't run `migrate`.
- **`lastmod`**: only set `lastmod_resolver` (an invokable class-string, receives the `Illuminate\Routing\Route`, returns a `DateTimeInterface|null`) if the app can genuinely track when a route's content last changed. Google disregards a `lastmod` it can't trust more than it disregards a missing one - never wire this up to `now()` or anything that changes on every deploy.
- **Large sites**: once the scanned URL count exceeds `chunk_size` (default 50,000, matching the sitemaps.org/Google limit), `/sitemap.xml` automatically serves a `<sitemapindex>` instead of a flat `<urlset>`, with numbered pages at `?page=1`, `?page=2`, etc. Nothing to configure for this to work.
- **Named routes**: `route('sitemap.html')` / `route('sitemap.xml')`. Extra middleware aliases or classes go in `sitemap.middleware`.
- **Discovery**: a `Sitemap:` line in `public/robots.txt` is how a crawler actually finds `sitemap.xml` passively - not an HTML `<link>` tag (there isn't one, and adding one wouldn't help). This is kept in sync automatically after `sync_robots_after_commands` (default `['migrate']`) finishes successfully - same signal as the cache-clear above - since a console command can write to `public/` in a way a real HTTP request often can't: creates `robots.txt` if missing (allowing all crawling), appends the `Sitemap:` line if missing, or logs a warning (never overwrites) if an existing `Sitemap:` line points somewhere else - that's a possible misconfiguration only a human can judge. Run `php artisan sitemap:link-robots` to apply the same logic immediately rather than waiting for the next deploy.

### 3. Verify

- Run `php artisan route:list` and compare it against `/sitemap` - confirm every route you expect to be public actually appears, and nothing behind auth does.
- Fetch `/sitemap.xml` and confirm it validates as `sitemaps.org` XML (namespace `http://www.sitemaps.org/schemas/sitemap/0.9`, a `<loc>` per URL).
- If `lastmod_resolver` was added, confirm it returns `null` rather than a stale/incorrect date for routes it can't confidently date.
- If a route resolver was added, confirm the sitemap actually gained one entry per real item (not just the static parts of the URL, and not a single placeholder entry), and that `sitemap:clear` (or a deploy hook) picks up new items as they're added.
- Fetch `robots.txt` and confirm it has a `Sitemap:` line pointing at this app's XML sitemap URL.

## Rules, References, and Templates

Read before executing:

- `README.md` in the package root - full config reference and behaviour
- `config/sitemap.php` (published copy, if present) - inline documentation for every option

## Examples

- Excluding an admin panel entirely: add `'admin.*'` to `excluded_route_names` (or `'admin/*'` to `excluded_paths` if the routes are unnamed), rather than excluding each admin route individually.
- Matching the site's branding: publish the HTML view, wrap the existing `@foreach` markup in `@extends('layouts.app')` / `@section(...)`, and confirm both the HTML and XML routes still return `200`.
- A blog index at `/blog` (named `blog.index`, plain GET route - included automatically) with posts at `/blog/{slug}` (named `blog.show`): add `'blog.show' => \App\Sitemap\PostSlugs::class` to `route_resolvers`, with `PostSlugs::__invoke()` returning every published slug (from an Eloquent model, a static file, wherever the app's own data actually lives).
- Grouping several unrelated marketing pages (`/about-us`, `/six-point-plan`, `/our-history`, ...) under one "About Us" section to match the site's nav, without changing any of their URLs: wrap them in `Route::name('about-us.')->group(fn () => ...)`, giving each a short local name (`->name('index')`, `->name('plan')`, ...) rather than renaming them to repeat "about us" in every name.

## Anti-patterns

- do not document package internals here; keep the skill focused on adoption in Laravel apps
- do not add a `lastmod_resolver` that can't distinguish "content changed" from "just rebuilt" - Google explicitly disregards untrustworthy `lastmod` values
- do not leave a content-listing route's `{parameter}` unresolved just because it takes an extra config line - a resolver is cheap and this is what turns "the sitemap only has the homepage" into a sitemap worth having
- do not register a `Closure` as a route resolver or `lastmod_resolver` - both must survive `config:cache`, so only an invokable class-string or `'\Class@method'` string is allowed
- do not exclude routes one name at a time when a single middleware or wildcard pattern would do
- do not add an HTML `<link>` tag pointing at the XML sitemap thinking it aids discovery - crawlers don't use it; `robots.txt`'s `Sitemap:` line is what matters, and it's kept in sync automatically after a deploy
- do not treat "both an HTML and an XML sitemap link to the same URLs" as a duplicate-content risk - it isn't one; Google's own guidance recommends having both
- do not assume a fresh install has a correct `Sitemap:` line yet if `sync_robots_after_commands` hasn't fired since - run `php artisan sitemap:link-robots` directly to apply it immediately instead of waiting

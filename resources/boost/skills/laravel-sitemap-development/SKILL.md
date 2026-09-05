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

- **How it works**: the package scans `Route::getRoutes()` on every cache miss - there is no separate sitemap definition to maintain. Only `GET`/`HEAD` routes with no unresolved `{parameter}`, no excluded middleware, and no vendor-package origin are included. Routes needing a concrete model instance per URL (e.g. `/posts/{post}`) are never included - this package has no per-route resolver. `Route::redirect()`/`Route::permanentRedirect()` routes are always excluded (never point a crawler at a 3xx); `Route::view()` routes are included normally.
- **Custom HTML**: publish the view (`--tag=sitemap-views`) and edit `resources/views/vendor/sitemap/html.blade.php` directly to extend the app's own layout. It receives `$groups` (a `Collection<string, Collection<int, SitemapUrl>>` keyed by URL group, alphabetically sorted). Never invent new variables here - treat that as a fixed contract.
- **Excluding routes**: prefer `excluded_middleware` (matches by middleware name/alias, e.g. `auth`, `auth:*`) over enumerating route names one by one. Use `excluded_route_names` / `excluded_paths` (both support `Str::is()` wildcards) for anything middleware can't express.
- **Cache**: the scanned route list is cached for `cache_seconds` (default 3600). It's also cleared automatically after `clear_cache_after_commands` (default `['migrate']`) finishes successfully - Laravel has no single "deployment finished" event, so this piggybacks on a command almost every deploy already runs. Add to that list, run `php artisan sitemap:clear` manually, or lower/zero `cache_seconds` if the app's deploy doesn't run `migrate`.
- **`lastmod`**: only set `lastmod_resolver` (an invokable class-string, receives the `Illuminate\Routing\Route`, returns a `DateTimeInterface|null`) if the app can genuinely track when a route's content last changed. Google disregards a `lastmod` it can't trust more than it disregards a missing one - never wire this up to `now()` or anything that changes on every deploy.
- **Large sites**: once the scanned URL count exceeds `chunk_size` (default 50,000, matching the sitemaps.org/Google limit), `/sitemap.xml` automatically serves a `<sitemapindex>` instead of a flat `<urlset>`, with numbered pages at `?page=1`, `?page=2`, etc. Nothing to configure for this to work.
- **Named routes**: `route('sitemap.html')` / `route('sitemap.xml')`. Extra middleware aliases or classes go in `sitemap.middleware`.

### 3. Verify

- Run `php artisan route:list` and compare it against `/sitemap` - confirm every route you expect to be public actually appears, and nothing behind auth does.
- Fetch `/sitemap.xml` and confirm it validates as `sitemaps.org` XML (namespace `http://www.sitemaps.org/schemas/sitemap/0.9`, a `<loc>` per URL).
- If `lastmod_resolver` was added, confirm it returns `null` rather than a stale/incorrect date for routes it can't confidently date.

## Rules, References, and Templates

Read before executing:

- `README.md` in the package root - full config reference and behaviour
- `config/sitemap.php` (published copy, if present) - inline documentation for every option

## Examples

- Excluding an admin panel entirely: add `'admin.*'` to `excluded_route_names` (or `'admin/*'` to `excluded_paths` if the routes are unnamed), rather than excluding each admin route individually.
- Matching the site's branding: publish the HTML view, wrap the existing `@foreach` markup in `@extends('layouts.app')` / `@section(...)`, and confirm both the HTML and XML routes still return `200`.

## Anti-patterns

- do not document package internals here; keep the skill focused on adoption in Laravel apps
- do not add a `lastmod_resolver` that can't distinguish "content changed" from "just rebuilt" - Google explicitly disregards untrustworthy `lastmod` values
- do not reach for a per-route model-enumeration workaround for parameterised routes - that's an explicit v1 limitation, not a bug
- do not exclude routes one name at a time when a single middleware or wildcard pattern would do

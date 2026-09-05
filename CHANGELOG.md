# Release Notes

## [Unreleased](https://github.com/gavtaylor/laravel-sitemap/compare/v0.1.0...main)

## [v0.1.0](https://github.com/gavtaylor/laravel-sitemap/releases/tag/v0.1.0) - 2026-09-05

Initial release. Scans an application's own registered routes to serve an HTML sitemap for human visitors and a `sitemaps.org`-protocol XML sitemap for search engines - no separate list of URLs to keep in sync.

- Two auto-registered routes, `/sitemap` (HTML, grouped by URL segment, alphabetical) and `/sitemap.xml` (`sitemaps.org` protocol), both configurable.
- Only safe-to-publish routes are included: `GET`/`HEAD`, no `{parameter}`, no `#` fragment, not behind `auth`/`signed`/other excluded middleware, not owned by a vendor package, and never `Route::redirect()`/`Route::permanentRedirect()`.
- The scanned route list is cached (`cache_seconds`) rather than recomputed on every request, and cleared automatically after `artisan migrate` (or any configured command) finishes, since Laravel has no single "deployment finished" event to hook into. A `sitemap:clear` command is also available.
- Serves a `<sitemapindex>` of numbered chunks once the URL count exceeds `chunk_size` (default 50,000, matching Google's per-file limit), instead of a flat `<urlset>`.
- No `<priority>`/`<changefreq>` in the XML output - Google's own documentation says both are ignored. An optional `lastmod_resolver` callback can supply a trustworthy `<lastmod>` per route; none is guessed.
- Publishable HTML view (`vendor:publish --tag=sitemap-views`) so the sitemap page can extend the app's own layout.

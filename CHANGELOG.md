# Release Notes

## [Unreleased](https://github.com/gavtaylor/laravel-sitemap/compare/v0.2.0...main)

## [v0.2.0](https://github.com/gavtaylor/laravel-sitemap/releases/tag/v0.2.0) - 2026-09-05

- `SitemapUrl` now carries a human-readable `label` (derived from the route name, e.g. `clients.index` -> "Clients"; falls back to the last URI segment for an unnamed route). The bundled HTML view uses it as link text instead of the raw URL, and sorts/groups by it.
- A `{parameter}` route (e.g. `/blog/{slug}`) can now be included via a `route_resolvers` config entry, keyed by route name, pointing at a callable that enumerates the route's concrete values - with an optional exact label and `lastmod` per item. Previously these routes were always excluded with no way to opt back in.
- `lastmod_resolver` (and a route resolver's per-item `lastmod`) now actually accepts a `string` date as documented, not just a `DateTimeInterface` - a plain date string was previously silently discarded.
- The HTML view folds a single-page segment (e.g. `/about`, `/contact`) into a "General" section alongside the homepage, instead of giving it a one-item section of its own - a group is only worth having once it actually groups more than one page (e.g. `/blog` and its posts). "General" always comes first, with the homepage pinned at the top of it.

## [v0.1.0](https://github.com/gavtaylor/laravel-sitemap/releases/tag/v0.1.0) - 2026-09-05

Initial release. Scans an application's own registered routes to serve an HTML sitemap for human visitors and a `sitemaps.org`-protocol XML sitemap for search engines - no separate list of URLs to keep in sync.

- Two auto-registered routes, `/sitemap` (HTML, grouped by URL segment, alphabetical) and `/sitemap.xml` (`sitemaps.org` protocol), both configurable.
- Only safe-to-publish routes are included: `GET`/`HEAD`, no `{parameter}`, no `#` fragment, not behind `auth`/`signed`/other excluded middleware, not owned by a vendor package, and never `Route::redirect()`/`Route::permanentRedirect()`.
- The scanned route list is cached (`cache_seconds`) rather than recomputed on every request, and cleared automatically after `artisan migrate` (or any configured command) finishes, since Laravel has no single "deployment finished" event to hook into. A `sitemap:clear` command is also available.
- Serves a `<sitemapindex>` of numbered chunks once the URL count exceeds `chunk_size` (default 50,000, matching Google's per-file limit), instead of a flat `<urlset>`.
- No `<priority>`/`<changefreq>` in the XML output - Google's own documentation says both are ignored. An optional `lastmod_resolver` callback can supply a trustworthy `<lastmod>` per route; none is guessed.
- Publishable HTML view (`vendor:publish --tag=sitemap-views`) so the sitemap page can extend the app's own layout.

# Release Notes

## [Unreleased](https://github.com/gavtaylor/laravel-sitemap/compare/v0.6.0...main)

- `label_glossary` entries can now be a space-separated phrase (`'reach newsletter' => 'REACH Newsletter'`), matched only against that exact run of words, alongside the existing single-word form - a single-word entry corrects every occurrence of that word, which is wrong when the word has an unrelated, non-acronym meaning elsewhere in the app. Phrases are matched longest-first so a multi-word entry always wins over a shorter one that only matches its first word.

## [v0.6.0](https://github.com/gavtaylor/laravel-sitemap/releases/tag/v0.6.0) - 2026-09-06

- New `label_glossary` config - a word-level casing dictionary (e.g. `'eca' => 'ECA'`) applied to every generated label and group heading, so an acronym that `Str::headline()` would otherwise flatten to title case ("Eca Committee") reads correctly ("ECA Committee") everywhere it appears, from one entry rather than a full-label override per affected route.

## [v0.5.0](https://github.com/gavtaylor/laravel-sitemap/releases/tag/v0.5.0) - 2026-09-05

- `robots.txt` is now kept in sync automatically after a deploy (the same `sync_robots_after_commands` signal used for cache-clearing, default `['migrate']`), instead of relying on someone remembering to run `sitemap:link-robots` manually - this was found missing on multiple real sites after install. Creates the file if it's missing entirely (allowing all crawling), appends the `Sitemap:` line if the file exists without one, and logs a warning - without ever overwriting it - if an existing `Sitemap:` line points somewhere else, since that's a misconfiguration only a human can judge. `RobotsTxtWarning` is replaced by `RobotsTxtSync`, which does both the passive check and the new writing.

## [v0.4.0](https://github.com/gavtaylor/laravel-sitemap/releases/tag/v0.4.0) - 2026-09-05

- The HTML sitemap now groups a route by its own name prefix first (Laravel's native `Route::name('about-us.')->group(...)` convention) before falling back to its URL segment - lets otherwise-unrelated pages be grouped together (e.g. to match a site's nav menu) without renaming or moving any URL. A route's label strips a redundant leading repeat of its group name (`about-us.plan` -> label "About Us Plan" -> "Plan", since "About Us" is already the section heading).

## [v0.3.0](https://github.com/gavtaylor/laravel-sitemap/releases/tag/v0.3.0) - 2026-09-05

- New `sitemap:link-robots` command adds a `Sitemap:` line to `public/robots.txt` pointing at the XML sitemap - the actual mechanism crawlers use for passive discovery (an HTML `<link>` tag doesn't help). A boot-time warning points at the command if `robots.txt` exists without that line. Not automatic: `robots.txt` is normally a static file the web server serves directly, so a route can't safely add it, and the package can't assume `public/` is writable in production.
- Fixed: a route resolver (`route_resolvers`) could fail with `Route [...] not defined` when the sitemap was scanned without a prior HTTP dispatch in the same request (e.g. from a console command) - `RouteScanner` now builds the resolved URL directly from the route's own URI pattern instead of re-looking it up by name via the global `route()` helper, which depends on a route-name index that Laravel only refreshes as a side effect of matching a real request.

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

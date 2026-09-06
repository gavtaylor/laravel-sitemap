<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled
    |--------------------------------------------------------------------------
    |
    | Whether this package registers its sitemap routes at all.
    |
    */

    'enabled' => env('SITEMAP_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | HTML path
    |--------------------------------------------------------------------------
    |
    | The URI the human-readable HTML sitemap is served at.
    |
    */

    'path' => env('SITEMAP_PATH', '/sitemap'),

    /*
    |--------------------------------------------------------------------------
    | XML path
    |--------------------------------------------------------------------------
    |
    | The URI the XML sitemap is served at, in the format search engines
    | expect (the sitemaps.org protocol - https://www.sitemaps.org/protocol.html).
    | Point Google Search Console / robots.txt at this URL.
    |
    */

    'xml_path' => env('SITEMAP_XML_PATH', '/sitemap.xml'),

    /*
    |--------------------------------------------------------------------------
    | Route name prefix
    |--------------------------------------------------------------------------
    |
    | Both routes are named using this prefix (`{prefix}.html`, `{prefix}.xml`)
    | so the app can generate URLs with route('sitemap.html') /
    | route('sitemap.xml'). Also used to recognise and exclude this
    | package's own routes from the scan, so the sitemap never lists itself.
    |
    */

    'route_name_prefix' => env('SITEMAP_ROUTE_NAME_PREFIX', 'sitemap'),

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | How long (in seconds) the scanned route list is cached for, so the
    | route table is only walked once per window rather than on every
    | request to either sitemap. Set to 0 to always scan fresh. Routes
    | rarely change outside of a deploy - see clear_cache_after_commands
    | below for how the cache is kept fresh automatically, or run
    | `php artisan sitemap:clear` manually.
    |
    */

    'cache_seconds' => env('SITEMAP_CACHE_SECONDS', 3600),

    /*
    |--------------------------------------------------------------------------
    | Clear cache after these Artisan commands
    |--------------------------------------------------------------------------
    |
    | Laravel has no single "deployment finished" event, but almost every
    | deploy runs `artisan migrate` - so the sitemap cache is cleared
    | automatically after any of these commands finish successfully,
    | rather than relying only on the TTL above or a manual
    | `php artisan sitemap:clear`. Set to an empty array to disable.
    |
    */

    'clear_cache_after_commands' => array_values(array_filter(explode(',', (string) env(
        'SITEMAP_CLEAR_CACHE_AFTER_COMMANDS',
        'migrate',
    )))),

    /*
    |--------------------------------------------------------------------------
    | Sync robots.txt after these Artisan commands
    |--------------------------------------------------------------------------
    |
    | A crawler discovers the XML sitemap via a `Sitemap:` line in
    | robots.txt - not automatically, since robots.txt is normally a static
    | file the web server returns directly (see the "Excluded" note on
    | `sitemap:link-robots` in the README). Rather than relying on that
    | command being remembered, the same commands configured above also
    | trigger an automatic check after they finish successfully: create
    | robots.txt if it's missing entirely (allowing all crawling), or add
    | the Sitemap: line if the file exists without one. If robots.txt
    | already has a *different* Sitemap: line, it's left alone and a
    | warning is logged instead - that's a human decision (a stale entry
    | from before a domain change, say), not something to silently
    | overwrite. Set to an empty array to disable.
    |
    */

    'sync_robots_after_commands' => array_values(array_filter(explode(',', (string) env(
        'SITEMAP_SYNC_ROBOTS_AFTER_COMMANDS',
        'migrate',
    )))),

    /*
    |--------------------------------------------------------------------------
    | Chunk size
    |--------------------------------------------------------------------------
    |
    | The sitemaps.org protocol (followed by Google, Bing, and others) caps
    | a single sitemap file at 50,000 URLs / 50MB uncompressed. If the
    | scanned route list exceeds this many URLs, the XML sitemap serves a
    | <sitemapindex> of numbered child sitemaps instead of a flat <urlset>.
    | Leave this at the protocol maximum unless you have a specific reason
    | to serve smaller files.
    |
    */

    'chunk_size' => env('SITEMAP_CHUNK_SIZE', 50000),

    /*
    |--------------------------------------------------------------------------
    | Extra middleware
    |--------------------------------------------------------------------------
    |
    | Additional middleware classes or aliases to run on both sitemap
    | routes, for example throttling.
    |
    */

    'middleware' => array_values(array_filter(explode(',', (string) env('SITEMAP_MIDDLEWARE', '')))),

    /*
    |--------------------------------------------------------------------------
    | Excluded middleware
    |--------------------------------------------------------------------------
    |
    | A route carrying any of these middleware names (matched with
    | Str::is(), so wildcards like 'auth:*' work) is left out of the
    | sitemap. The default list excludes anything gated behind
    | authentication or a signed URL - routes that search engines and
    | anonymous visitors could never actually reach.
    |
    */

    'excluded_middleware' => explode(',', (string) env(
        'SITEMAP_EXCLUDED_MIDDLEWARE',
        'auth,auth:*,signed,password.confirm',
    )),

    /*
    |--------------------------------------------------------------------------
    | Excluded route names
    |--------------------------------------------------------------------------
    |
    | Route names to leave out of the sitemap, matched with Str::is() so
    | wildcards work (e.g. 'admin.*').
    |
    */

    'excluded_route_names' => array_filter(explode(',', (string) env('SITEMAP_EXCLUDED_ROUTE_NAMES', ''))),

    /*
    |--------------------------------------------------------------------------
    | Excluded paths
    |--------------------------------------------------------------------------
    |
    | Route URIs to leave out of the sitemap, matched with Str::is() so
    | wildcards work (e.g. 'admin/*').
    |
    */

    'excluded_paths' => array_filter(explode(',', (string) env('SITEMAP_EXCLUDED_PATHS', ''))),

    /*
    |--------------------------------------------------------------------------
    | Route resolvers
    |--------------------------------------------------------------------------
    |
    | A route with a {parameter} (e.g. `/blog/{slug}`) has no single URL of
    | its own, so it's excluded from the sitemap unless a resolver is
    | registered for it here, keyed by route name. A resolver is a callable
    | (class-string of an invokable class, or a '\Class@method' string - not
    | a Closure, so this file stays safe to cache with `config:cache`) that
    | returns an iterable of the concrete values that route actually has.
    | Each item can be a plain value for a single-parameter route:
    |
    |   'blog.show' => \App\Sitemap\PostSlugs::class,
    |
    |   final class PostSlugs
    |   {
    |       public function __invoke(): array
    |       {
    |           return array_keys(require resource_path('writing/posts.php'));
    |       }
    |   }
    |
    | ...or an array with 'parameters' (required - a map of every route
    | parameter to its value), and optionally 'label' and 'lastmod', for
    | full control (e.g. a route with more than one parameter, or wanting
    | the sitemap to show a real title instead of a humanised slug):
    |
    |   ['parameters' => ['slug' => 'my-post'], 'label' => 'My Post', 'lastmod' => '2026-01-01']
    |
    */

    'route_resolvers' => [
        // 'blog.show' => \App\Sitemap\PostSlugs::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Last modified resolver
    |--------------------------------------------------------------------------
    |
    | An optional callable (class-string of an invokable class, or a
    | '\Class@method' string) receiving the Illuminate\Routing\Route and
    | returning a DateTimeInterface|string|null to use as that URL's
    | <lastmod>. Left null by default: Google's own guidance is that an
    | untrustworthy lastmod is worse than none, and this package has no
    | way to know when a route's content last changed.
    |
    */

    'lastmod_resolver' => env('SITEMAP_LASTMOD_RESOLVER'),

    /*
    |--------------------------------------------------------------------------
    | Label glossary
    |--------------------------------------------------------------------------
    |
    | Every label and group heading is built by running Str::headline() over
    | a route name or URL segment, which title-cases each word but has no
    | idea an acronym should stay fully uppercase - 'eca-committee' headlines
    | to "Eca Committee", not "ECA Committee". Rather than overriding the
    | full label of every affected route by hand, list the correction once
    | here, keyed by the lowercase word as Str::headline() produces it - it's
    | then applied to that word wherever it appears, in any label or group:
    |
    |   'eca' => 'ECA',
    |
    | fixes "Eca Committee", "Eca 2026 Candidate", and every other headlined
    | word "Eca" alike, with nothing to maintain per-route as new ones are
    | added.
    |
    */

    'label_glossary' => [],

];

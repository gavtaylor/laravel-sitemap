<?php

declare(strict_types=1);

use GavTaylor\Sitemap\RouteScanner;
use GavTaylor\Sitemap\SitemapUrl;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

function scannedUrls(): array
{
    return app(RouteScanner::class)->scan();
}

function scannedUris(): array
{
    return array_map(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) ?? '/', scannedUrls());
}

it('includes a plain GET route', function () {
    RouteFacade::get('/about', fn () => 'about')->name('about');

    expect(scannedUris())->toContain('/about');
});

it('excludes a route with a required parameter', function () {
    RouteFacade::get('/posts/{post}', fn (string $post) => $post)->name('posts.show');

    expect(scannedUris())->not->toContain('/posts/{post}');
});

it('excludes a route with an optional parameter', function () {
    RouteFacade::get('/archive/{year?}', fn (?string $year = null) => $year)->name('archive');

    expect(scannedUris())->not->toContain('/archive/{year?}');
});

it('excludes a Route::redirect() route', function () {
    RouteFacade::redirect('/old-page', '/new-page', 301);

    expect(scannedUris())->not->toContain('/old-page');
});

it('excludes a Route::permanentRedirect() route', function () {
    RouteFacade::permanentRedirect('/legacy-page', '/current-page');

    expect(scannedUris())->not->toContain('/legacy-page');
});

it('excludes a route whose URI contains a fragment', function () {
    RouteFacade::get('/pricing#annual', fn () => '')->name('pricing.annual');

    $urls = collect(scannedUrls())->map(fn (SitemapUrl $url) => $url->url);

    expect($urls)->not->toContain(url('pricing#annual'));
});

it('does not exclude the fragment route\'s base path when it is registered separately', function () {
    RouteFacade::get('/pricing', fn () => '')->name('pricing');
    RouteFacade::get('/pricing#annual', fn () => '')->name('pricing.annual');

    $urls = collect(scannedUrls())->map(fn (SitemapUrl $url) => $url->url);

    expect($urls)->toContain(url('pricing'))->not->toContain(url('pricing#annual'));
});

it('includes a Route::view() route', function () {
    RouteFacade::view('/terms', 'welcome')->name('terms');

    expect(scannedUris())->toContain('/terms');
});

it('excludes a route behind auth middleware', function () {
    RouteFacade::get('/account', fn () => '')->middleware('auth')->name('account');

    expect(scannedUris())->not->toContain('/account');
});

it('excludes a route that only responds to POST', function () {
    RouteFacade::post('/contact', fn () => '')->name('contact.store');

    expect(scannedUris())->not->toContain('/contact');
});

it('excludes routes matching a configured excluded route name pattern', function () {
    config(['sitemap.excluded_route_names' => ['admin.*']]);

    RouteFacade::get('/admin/dashboard', fn () => '')->name('admin.dashboard');

    expect(scannedUris())->not->toContain('/admin/dashboard');
});

it('excludes routes matching a configured excluded path pattern', function () {
    config(['sitemap.excluded_paths' => ['internal/*']]);

    RouteFacade::get('/internal/status', fn () => '')->name('internal.status');

    expect(scannedUris())->not->toContain('/internal/status');
});

it('groups a root-level route under general', function () {
    RouteFacade::get('/', fn () => '')->name('home');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => (parse_url($url->url, PHP_URL_PATH) ?? '/') === '/');

    expect($url->group)->toBe('General');
});

it('groups a nested route by its first path segment', function () {
    RouteFacade::get('/blog/latest', fn () => '')->name('blog.latest');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/blog/latest');

    expect($url->group)->toBe('Blog');
});

it('groups routes by their name prefix instead of their URL segment', function () {
    RouteFacade::name('about-us.')->group(function () {
        RouteFacade::get('/about-us', fn () => '')->name('index');
        RouteFacade::get('/six-point-plan', fn () => '')->name('plan');
    });

    $urls = collect(scannedUrls())->filter(
        fn (SitemapUrl $url) => in_array(parse_url($url->url, PHP_URL_PATH), ['/about-us', '/six-point-plan'], true),
    );

    expect($urls)->toHaveCount(2);
    expect($urls->pluck('group')->unique()->all())->toBe(['About Us']);
});

it('strips a redundant leading group name from a name-prefix-grouped route\'s label', function () {
    RouteFacade::name('about-us.')->group(function () {
        RouteFacade::get('/six-point-plan', fn () => '')->name('six-point-plan');
    });

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/six-point-plan');

    expect($url->group)->toBe('About Us');
    expect($url->label)->toBe('Six Point Plan');
});

it('falls back to the URL segment when the route name has no prefix', function () {
    RouteFacade::get('/contact', fn () => '')->name('contact');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/contact');

    expect($url->group)->toBe('Contact');
});

it('does not include this package\'s own sitemap routes', function () {
    $names = collect(app('router')->getRoutes())->map(fn (Route $route) => $route->getName());

    expect($names)->toContain('sitemap.html', 'sitemap.xml');
    expect(scannedUris())->not->toContain('/sitemap', '/sitemap.xml');
});

it('resolves lastmod through the configured resolver', function () {
    config(['sitemap.lastmod_resolver' => StubLastmodResolver::class]);

    RouteFacade::get('/updated', fn () => '')->name('updated');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/updated');

    expect($url->lastmod)->not->toBeNull();
    expect($url->lastmod->format('Y-m-d'))->toBe('2026-01-01');
});

it('resolves a string lastmod through the configured resolver', function () {
    config(['sitemap.lastmod_resolver' => StubStringLastmodResolver::class]);

    RouteFacade::get('/updated-string', fn () => '')->name('updated-string');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/updated-string');

    expect($url->lastmod)->not->toBeNull();
    expect($url->lastmod->format('Y-m-d'))->toBe('2026-02-14');
});

it('labels a named route from its route name', function () {
    RouteFacade::get('/about', fn () => '')->name('about');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/about');

    expect($url->label)->toBe('About');
});

it('drops a resource-controller "index" suffix from the label', function () {
    RouteFacade::get('/clients', fn () => '')->name('clients.index');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/clients');

    expect($url->label)->toBe('Clients');
});

it('labels an unnamed route from its last URI segment', function () {
    RouteFacade::get('/about-us', fn () => '');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/about-us');

    expect($url->label)->toBe('About Us');
});

it('applies a configured label glossary word to a route label', function () {
    config(['sitemap.label_glossary' => ['eca' => 'ECA']]);

    RouteFacade::get('/eca-committee', fn () => '')->name('eca-committee');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/eca-committee');

    expect($url->label)->toBe('ECA Committee');
});

it('applies a configured label glossary word to a name-prefix group heading', function () {
    config(['sitemap.label_glossary' => ['eca' => 'ECA']]);

    RouteFacade::name('eca.')->group(function () {
        RouteFacade::get('/eca-events', fn () => '')->name('events');
    });

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/eca-events');

    expect($url->group)->toBe('ECA');
});

it('matches a configured label glossary word case-insensitively', function () {
    config(['sitemap.label_glossary' => ['ECA' => 'ECA']]);

    RouteFacade::get('/eca-committee', fn () => '')->name('eca-committee');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/eca-committee');

    expect($url->label)->toBe('ECA Committee');
});

it('leaves labels untouched when no label glossary is configured', function () {
    config(['sitemap.label_glossary' => []]);

    RouteFacade::get('/eca-committee', fn () => '')->name('eca-committee');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/eca-committee');

    expect($url->label)->toBe('Eca Committee');
});

it('resolves a parameterized route with a registered resolver', function () {
    config(['sitemap.route_resolvers' => ['blog.show' => StubSlugResolver::class]]);

    RouteFacade::get('/blog/{slug}', fn (string $slug) => $slug)->name('blog.show');

    $urls = collect(scannedUrls())->filter(fn (SitemapUrl $url) => str_contains($url->url, '/blog/'));

    expect($urls->pluck('url')->all())->toContain(url('blog/first-post'), url('blog/second-post'));
    expect($urls->firstWhere('url', url('blog/first-post'))->label)->toBe('First Post');
});

it('resolves a parameterized route with an explicit label and lastmod', function () {
    config(['sitemap.route_resolvers' => ['blog.show' => StubDetailedResolver::class]]);

    RouteFacade::get('/blog/{slug}', fn (string $slug) => $slug)->name('blog.show');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => $url->url === url('blog/hello-world'));

    expect($url->label)->toBe('Hello, World!');
    expect($url->lastmod->format('Y-m-d'))->toBe('2026-03-01');
});

it('does not resolve a parameterized route with no registered resolver', function () {
    RouteFacade::get('/blog/{slug}', fn (string $slug) => $slug)->name('blog.show');

    expect(scannedUris())->not->toContain('/blog/{slug}');
});

final class StubLastmodResolver
{
    public function __invoke(Route $route): DateTimeInterface
    {
        return new DateTimeImmutable('2026-01-01');
    }
}

final class StubStringLastmodResolver
{
    public function __invoke(Route $route): string
    {
        return '2026-02-14';
    }
}

final class StubSlugResolver
{
    public function __invoke(Route $route): array
    {
        return ['first-post', 'second-post'];
    }
}

final class StubDetailedResolver
{
    public function __invoke(Route $route): array
    {
        return [
            [
                'parameters' => ['slug' => 'hello-world'],
                'label' => 'Hello, World!',
                'lastmod' => '2026-03-01',
            ],
        ];
    }
}

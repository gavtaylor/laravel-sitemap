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

    expect($url->group)->toBe('general');
});

it('groups a nested route by its first path segment', function () {
    RouteFacade::get('/blog/latest', fn () => '')->name('blog.latest');

    $url = collect(scannedUrls())->first(fn (SitemapUrl $url) => parse_url($url->url, PHP_URL_PATH) === '/blog/latest');

    expect($url->group)->toBe('blog');
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

final class StubLastmodResolver
{
    public function __invoke(Route $route): DateTimeInterface
    {
        return new DateTimeImmutable('2026-01-01');
    }
}

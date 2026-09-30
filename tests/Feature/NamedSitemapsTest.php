<?php

declare(strict_types=1);

use GavTaylor\Sitemap\SitemapCache;
use GavTaylor\Sitemap\SitemapServiceProvider;
use GavTaylor\Sitemap\SitemapUrl;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * Named-sitemap config is read again here because the application has
 * already booted with an empty `sitemaps` list. Boot calls the same method.
 */
function configureNewsSitemap(): void
{
    config([
        'sitemap.sitemaps' => [
            'news' => [
                'xml_path' => '/sitemap-news.xml',
                'route_names' => ['news.show'],
            ],
        ],
        'sitemap.route_resolvers' => [
            'news.show' => NewsSlugResolver::class,
        ],
    ]);

    $provider = app()->getProvider(SitemapServiceProvider::class);

    expect($provider)->toBeInstanceOf(SitemapServiceProvider::class);

    $provider->registerNamedSitemapRoutes();
}

it('serves an index that links the page list and the named sitemap', function () {
    configureNewsSitemap();
    NewsSlugResolver::$slugs = ['grant-funding-open'];

    RouteFacade::get('/about', fn () => '')->name('about');
    RouteFacade::get('/news', fn () => '')->name('news.index');
    RouteFacade::get('/news/{slug}', fn (string $slug) => $slug)->name('news.show');

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<sitemapindex', false)
        ->assertSee('<loc>'.url('/sitemap-pages.xml').'</loc>', false)
        ->assertSee('<loc>'.url('/sitemap-news.xml').'</loc>', false)
        ->assertDontSee('<loc>'.url('/news/grant-funding-open').'</loc>', false);

    $this->get('/sitemap-pages.xml')
        ->assertOk()
        ->assertSee('<urlset', false)
        ->assertSee('<loc>'.url('/about').'</loc>', false)
        ->assertSee('<loc>'.url('/news').'</loc>', false)
        ->assertDontSee('<loc>'.url('/news/grant-funding-open').'</loc>', false);

    $this->get('/sitemap-news.xml')
        ->assertOk()
        ->assertSee('<urlset', false)
        ->assertSee('<loc>'.url('/news/grant-funding-open').'</loc>', false)
        ->assertDontSee('<loc>'.url('/about').'</loc>', false);

    $this->get('/sitemap')
        ->assertOk()
        ->assertSee('About')
        ->assertDontSee('Grant Funding Open');
});

it('lists a named sitemap that exceeds the chunk size as page files', function () {
    configureNewsSitemap();
    config(['sitemap.chunk_size' => 1]);
    NewsSlugResolver::$slugs = ['first-story', 'second-story'];

    RouteFacade::get('/news/{slug}', fn (string $slug) => $slug)->name('news.show');

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<loc>'.url('/sitemap-news.xml').'?page=1</loc>', false)
        ->assertSee('<loc>'.url('/sitemap-news.xml').'?page=2</loc>', false)
        ->assertDontSee('<loc>'.url('/sitemap-news.xml').'</loc>', false);

    $this->get('/sitemap-news.xml')
        ->assertOk()
        ->assertSee('<sitemapindex', false);

    $this->get('/sitemap-news.xml?page=1')
        ->assertOk()
        ->assertSee('<urlset', false);
});

it('keeps a single urlset when a named sitemap has no route names', function () {
    config(['sitemap.sitemaps' => [
        'news' => ['xml_path' => '/sitemap-news.xml'],
    ]]);

    RouteFacade::get('/about', fn () => '')->name('about');

    $this->get('/sitemap.xml')
        ->assertOk()
        ->assertSee('<urlset', false)
        ->assertDontSee('<sitemapindex', false)
        ->assertSee('<loc>'.url('/about').'</loc>', false);
});

it('still returns named-sitemap urls from the unfiltered cache', function () {
    configureNewsSitemap();
    NewsSlugResolver::$slugs = ['grant-funding-open'];

    RouteFacade::get('/about', fn () => '')->name('about');
    RouteFacade::get('/news/{slug}', fn (string $slug) => $slug)->name('news.show');

    $urls = collect(app(SitemapCache::class)->get())->map(fn (SitemapUrl $url): string => $url->url);

    expect($urls->all())->toContain(url('/about'), url('/news/grant-funding-open'));
});

final class NewsSlugResolver
{
    /** @var list<string> */
    public static array $slugs = [];

    /**
     * @return list<string>
     */
    public function __invoke(): array
    {
        return self::$slugs;
    }
}

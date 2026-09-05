<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route as RouteFacade;

it('serves the html sitemap', function () {
    $this->get(config('sitemap.path'))->assertOk();
});

it('lists registered pages alphabetically within their group', function () {
    RouteFacade::get('/blog/zebra', fn () => '')->name('blog.zebra');
    RouteFacade::get('/blog/apple', fn () => '')->name('blog.apple');

    $this->get(config('sitemap.path'))
        ->assertOk()
        ->assertSeeInOrder(['Apple', 'Zebra']);
});

it('shows a human-readable label as the link text, not the raw URL', function () {
    RouteFacade::get('/about', fn () => '')->name('about');

    $response = $this->get(config('sitemap.path'))->assertOk();

    $response->assertSee('<a href="'.url('/about').'">', false);
    $response->assertSee('About');
});

it('lists the homepage\'s group first regardless of alphabetical order', function () {
    RouteFacade::get('/', fn () => '')->name('home');
    RouteFacade::get('/zoo/one', fn () => '')->name('zoo.one');
    RouteFacade::get('/zoo/two', fn () => '')->name('zoo.two');

    $this->get(config('sitemap.path'))
        ->assertOk()
        ->assertSeeInOrder(['General', 'Zoo']);
});

it('folds a single-page group into general instead of giving it its own section', function () {
    RouteFacade::get('/', fn () => '')->name('home');
    RouteFacade::get('/about', fn () => '')->name('about');
    RouteFacade::get('/blog/one', fn () => '')->name('blog.one');
    RouteFacade::get('/blog/two', fn () => '')->name('blog.two');

    $response = $this->get(config('sitemap.path'))->assertOk();

    // "About" is the only page under its segment, so it's folded into
    // General rather than getting a section header of its own.
    $response->assertDontSee('<h2>About</h2>', false);
    $response->assertSeeInOrder(['General', 'Home', 'About', 'Blog']);
});

it('excludes a route behind auth middleware', function () {
    RouteFacade::get('/account', fn () => '')->middleware('auth')->name('account');

    $this->get(config('sitemap.path'))
        ->assertOk()
        ->assertDontSee(url('/account'));
});

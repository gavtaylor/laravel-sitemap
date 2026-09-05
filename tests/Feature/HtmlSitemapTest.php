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
        ->assertSeeInOrder([url('/blog/apple'), url('/blog/zebra')]);
});

it('excludes a route behind auth middleware', function () {
    RouteFacade::get('/account', fn () => '')->middleware('auth')->name('account');

    $this->get(config('sitemap.path'))
        ->assertOk()
        ->assertDontSee(url('/account'));
});

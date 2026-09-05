<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::get('/about', function () {
    return 'About us.';
})->name('about');

Route::get('/blog', function () {
    return 'Blog index.';
})->name('blog.index');

Route::get('/blog/{post}', function (string $post) {
    return "Post: {$post}";
})->name('blog.show');

Route::get('/account/settings', function () {
    return 'Account settings.';
})->middleware('auth')->name('account.settings');

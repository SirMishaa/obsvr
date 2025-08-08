<?php

use App\Http\Controllers\SocialiteController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('twitch', function () {
    return Inertia::render('Twitch', [
        'redirect' => route('socialite.redirect', ['provider' => 'twitch']),
    ]);
})->middleware(['auth', 'verified'])->name('twitch');

Route::prefix('auth/{provider}')
    ->where(['provider' => 'twitch'])
    ->group(function () {
        Route::get('redirect', [SocialiteController::class, 'redirect'])
            ->middleware(['guest'])
            ->name('socialite.redirect');
        Route::get('callback', [SocialiteController::class, 'handleCallback'])
            ->middleware(['guest'])
            ->name('socialite.callback');
    });

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

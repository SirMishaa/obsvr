<?php

use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\TwitchController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome');
})->name('home');

Route::get('/.well-known/appspecific/com.chrome.devtools.json', function () {
    if (app()->environment('local')) {
        return redirect()->away('http://localhost:5173/.well-known/appspecific/com.chrome.devtools.json', 307);
    }
    abort(404);
})->name('devtools');

Route::get('dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('twitch', [TwitchController::class, 'index'])->middleware(['auth', 'verified'])->name('twitch');

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

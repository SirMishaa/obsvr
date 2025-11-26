<?php

use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\TwitchController;
use App\Http\Controllers\TwitchEventSubController;
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

Route::prefix('twitch')->group(function () {
    Route::get('/', [TwitchController::class, 'index'])
        ->middleware(['auth', 'verified'])
        ->name('twitch');
    Route::post('favorite/{streamerId}', [TwitchController::class, 'toggleFavoriteStreamer'])
        ->name('twitch.favorite')
        ->middleware(['auth', 'verified']);
    Route::get('favorite/{favouriteStreamer:streamer_id}', [TwitchController::class, 'getStreamerEvents'])
        ->name('twitch.streamer.events')
        ->middleware(['auth', 'verified']);
});

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

Route::post('push/subscribe', [PushSubscriptionController::class, '__invoke'])
    ->middleware(['auth', 'verified'])
    ->name('push.subscription');

Route::post('twitch/eventsub', [TwitchEventSubController::class, 'handle'])
    ->middleware(\App\Http\Middleware\VerifyTwitchEventSubSignatureMiddleware::class)
    ->name('twitch.eventsub');

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';

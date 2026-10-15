<?php

declare(strict_types=1);

use App\Models\FavouriteStreamer;
use App\Models\User;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\Log;

beforeEach(function (): void {
    // Isolate the violation: essentials' automatic eager loading would otherwise resolve it silently.
    Model::automaticallyEagerLoadRelationships(false);
});

afterEach(function (): void {
    Model::handleLazyLoadingViolationUsing(null);
    Model::shouldBeStrict();
    Model::automaticallyEagerLoadRelationships();
});

function lazyLoadUserOfFirstFavourite(): void
{
    User::factory()->has(FavouriteStreamer::factory()->count(2), 'favouriteStreamers')->createQuietly();

    FavouriteStreamer::query()->get()->each(fn (FavouriteStreamer $favourite): ?User => $favourite->user);
}

test('lazy loading only logs a warning in production', function (): void {
    app()->detectEnvironment(fn (): string => 'production');
    $log = Log::spy();

    new AppServiceProvider(app())->boot();
    lazyLoadUserOfFirstFavourite();

    $log->shouldHaveReceived('warning')->withArgs(fn (string $message): bool => str_contains($message, 'Lazy loading'))->twice();
});

test('lazy loading throws outside production', function (): void {
    new AppServiceProvider(app())->boot();

    lazyLoadUserOfFirstFavourite();
})->throws(LazyLoadingViolationException::class);

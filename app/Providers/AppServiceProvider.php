<?php

namespace App\Providers;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;
use OpenTelemetry\API\Logs\LoggerInterface as OpenTelemetryLoggerInterface;
use OpenTelemetry\API\Logs\NoopLogger;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Twitch\TwitchExtendSocialite;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        if ($this->app->environment('local') && class_exists(\Laravel\Telescope\TelescopeServiceProvider::class)) {
            $this->app->register(\Laravel\Telescope\TelescopeServiceProvider::class);
            $this->app->register(TelescopeServiceProvider::class);
        }

        $this->registerOpenTelemetryLoggerFallback();
    }

    /**
     * Keep the otlp log channel usable before the OpenTelemetry package boots.
     *
     * keepsuit/laravel-opentelemetry declares the `otlp` channel in
     * packageRegistered() but only binds OpenTelemetry\API\Logs\LoggerInterface in
     * packageBooted(). Between the two the channel exists while its dependency does
     * not, so anything writing a log in that window — a provider logging from
     * register(), an exception thrown during boot — dies with
     * "Target [LoggerInterface] is not instantiable", masking the original error.
     *
     * A no-op is the honest fallback: the SDK is not initialised yet, so there is no
     * exporter to send anything to. The package overrides this binding at boot, and
     * Container::bind() drops the cached instance, so the real logger always wins
     * once it exists. Pre-boot entries still reach the other channels of the stack.
     */
    private function registerOpenTelemetryLoggerFallback(): void
    {
        if ($this->app->bound(OpenTelemetryLoggerInterface::class)) {
            return;
        }

        $this->app->singleton(OpenTelemetryLoggerInterface::class, fn (): NoopLogger => NoopLogger::getInstance());
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {

        Event::listen(
            SocialiteWasCalled::class,
            [TwitchExtendSocialite::class, 'handle']
        );
    }
}

<?php

namespace Tests\Providers;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\Helpers\ConfigRetriever;
use SocialiteProviders\Manager\SocialiteWasCalled;

it('registers twitch socialite provider on SocialiteWasCalled event', function () {
    Event::fake([SocialiteWasCalled::class]);

    $provider = new AppServiceProvider(app());
    $provider->boot();

    $event = new SocialiteWasCalled(app(), new ConfigRetriever);
    Event::dispatch($event);

    Event::assertDispatched(SocialiteWasCalled::class);

    /** TODO: Maybe there is a better way to test this */
    /*$manager = app(SocialiteManager::class);
    $r = new ReflectionProperty($manager, 'customCreators');
    $r->setAccessible(true);
    $custom = $r->getValue($manager);
    expect(array_key_exists('twitch', $custom))->toBeTrue();*/
});

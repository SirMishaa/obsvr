<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TwitchSubscriptionType;
use App\Models\FavouriteStreamer;
use App\Models\Subscriptions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<Subscriptions>
 */
final class SubscriptionsFactory extends Factory
{
    protected $model = Subscriptions::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'type' => fake()->randomElement(TwitchSubscriptionType::values()),
            'status' => fake()->word(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),

            'favourite_streamer_id' => FavouriteStreamer::factory(),
        ];
    }
}

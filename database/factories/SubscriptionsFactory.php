<?php

namespace Database\Factories;

use Illuminate\Support\Facades\Date;
use App\Enums\TwitchSubscriptionType;
use App\Models\FavouriteStreamer;
use App\Models\Subscriptions;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Subscriptions>
 */
class SubscriptionsFactory extends Factory
{
    protected $model = Subscriptions::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'type' => $this->faker->randomElement(TwitchSubscriptionType::values()),
            'status' => $this->faker->word(),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),

            'favourite_streamer_id' => FavouriteStreamer::factory(),
        ];
    }
}

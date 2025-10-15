<?php

namespace Database\Factories;

use App\Enums\TwitchSubscriptionType;
use App\Models\FavouriteStreamer;
use App\Models\Subscriptions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

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
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),

            'favourite_streamer_id' => FavouriteStreamer::factory(),
        ];
    }
}

<?php

namespace Database\Factories;

use Illuminate\Support\Facades\Date;
use App\Enums\TwitchSubscriptionStatus;
use App\Models\FavouriteStreamer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FavouriteStreamer>
 */
class FavouriteStreamerFactory extends Factory
{
    protected $model = FavouriteStreamer::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'streamer_id' => $this->faker->word(),
            'streamer_name' => $this->faker->word(),
            'subscription_status' => $this->faker->randomElement(TwitchSubscriptionStatus::values()),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ];
    }
}

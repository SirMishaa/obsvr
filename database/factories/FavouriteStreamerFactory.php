<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TwitchSubscriptionStatus;
use App\Models\FavouriteStreamer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Date;

/**
 * @extends Factory<FavouriteStreamer>
 */
final class FavouriteStreamerFactory extends Factory
{
    protected $model = FavouriteStreamer::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'streamer_id' => fake()->word(),
            'streamer_name' => fake()->word(),
            'subscription_status' => fake()->randomElement(TwitchSubscriptionStatus::values()),
            'created_at' => Date::now(),
            'updated_at' => Date::now(),
        ];
    }
}

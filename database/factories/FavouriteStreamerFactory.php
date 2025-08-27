<?php

namespace Database\Factories;

use App\Models\FavouriteStreamer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

class FavouriteStreamerFactory extends Factory
{
    protected $model = FavouriteStreamer::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'userId' => User::factory(),
            'streamer_id' => $this->faker->word(),
            'streamer_name' => $this->faker->word(),
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ];
    }
}

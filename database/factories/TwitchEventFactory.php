<?php

namespace Database\Factories;

use App\Models\TwitchEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TwitchEvent>
 */
class TwitchEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $eventTypes = ['stream.online', 'stream.offline', 'channel.update'];
        $eventType = fake()->randomElement($eventTypes);

        return [
            'event_id' => fake()->uuid(),
            'event_type' => $eventType,
            'streamer_id' => (string) fake()->numberBetween(10000000, 99999999),
            'streamer_name' => fake()->userName(),
            'payload' => $this->generatePayload($eventType),
            'occurred_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'received_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generatePayload(string $eventType): array
    {
        $basePayload = [
            'id' => fake()->uuid(),
            'broadcaster_user_id' => (string) fake()->numberBetween(10000000, 99999999),
            'broadcaster_user_login' => fake()->userName(),
            'broadcaster_user_name' => fake()->userName(),
            'type' => $eventType,
        ];

        return match ($eventType) {
            'stream.online' => array_merge($basePayload, [
                'started_at' => fake()->iso8601(),
            ]),
            'channel.update' => array_merge($basePayload, [
                'title' => fake()->sentence(),
                'language' => 'en',
                'category_id' => (string) fake()->numberBetween(1000, 9999),
                'category_name' => fake()->words(2, true),
            ]),
            default => $basePayload,
        };
    }

    public function streamOnline(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'stream.online',
            'payload' => $this->generatePayload('stream.online'),
        ]);
    }

    public function streamOffline(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'stream.offline',
            'payload' => $this->generatePayload('stream.offline'),
        ]);
    }

    public function channelUpdate(): static
    {
        return $this->state(fn (array $attributes) => [
            'event_type' => 'channel.update',
            'payload' => $this->generatePayload('channel.update'),
        ]);
    }
}

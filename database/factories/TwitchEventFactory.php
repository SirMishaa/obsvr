<?php

namespace Database\Factories;

use App\Models\TwitchEvent;
use Illuminate\Database\Eloquent\Factories\Factory;
use Tests\Fixtures\TwitchFixtures;

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
        $streamer = fake()->randomElement(TwitchFixtures::popularFrenchStreamers());

        return [
            'event_id' => $eventType === 'channel.update' ? null : fake()->bothify('########'),
            'event_type' => $eventType,
            'streamer_id' => $streamer['id'],
            'streamer_name' => $streamer['login'],
            'payload' => $this->generatePayload($eventType, $streamer),
            'occurred_at' => fake()->dateTimeBetween('-1 week', 'now'),
            'received_at' => now(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function generatePayload(string $eventType, array $streamer): array
    {
        $basePayload = [
            'id' => fake()->bothify('########'),
            'broadcaster_user_id' => $streamer['id'],
            'broadcaster_user_login' => $streamer['login'],
            'broadcaster_user_name' => $streamer['name'],
            'type' => $eventType,
        ];

        if ($eventType === 'channel.update') {
            unset($basePayload['id']);
        }

        return match ($eventType) {
            'stream.online' => array_merge($basePayload, [
                'started_at' => fake()->iso8601(),
            ]),
            'channel.update' => array_merge($basePayload, [
                'title' => fake()->randomElement(TwitchFixtures::streamTitles()),
                'language' => 'fr',
                'category_id' => ($category = fake()->randomElement(TwitchFixtures::popularCategories()))['id'],
                'category_name' => $category['name'],
            ]),
            default => $basePayload,
        };
    }

    public function streamOnline(): static
    {
        return $this->state(function (array $attributes) {
            $streamer = $this->getStreamerFromAttributes($attributes);

            return [
                'event_type' => 'stream.online',
                'payload' => $this->generatePayload('stream.online', $streamer),
            ];
        });
    }

    public function streamOffline(): static
    {
        return $this->state(function (array $attributes) {
            $streamer = $this->getStreamerFromAttributes($attributes);

            return [
                'event_type' => 'stream.offline',
                'payload' => $this->generatePayload('stream.offline', $streamer),
            ];
        });
    }

    public function channelUpdate(): static
    {
        return $this->state(function (array $attributes) {
            $streamer = $this->getStreamerFromAttributes($attributes);

            return [
                'event_type' => 'channel.update',
                'payload' => $this->generatePayload('channel.update', $streamer),
            ];
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array{id: string, login: string, name: string}
     */
    private function getStreamerFromAttributes(array $attributes): array
    {
        $streamers = TwitchFixtures::popularFrenchStreamers();
        $foundStreamer = collect($streamers)->firstWhere('id', $attributes['streamer_id']);

        return $foundStreamer ?? fake()->randomElement($streamers);
    }
}

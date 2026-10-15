<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LolNotificationType;
use App\Models\LolMatch;
use App\Models\LolMatchNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LolMatchNotification>
 */
final class LolMatchNotificationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'lol_match_id' => LolMatch::factory(),
            'user_id' => User::factory(),
            'type' => LolNotificationType::Reminder,
            'sent_at' => now(),
        ];
    }
}

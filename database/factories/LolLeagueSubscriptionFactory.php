<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LolLeague;
use App\Enums\LolSubscriptionMode;
use App\Models\LolLeagueSubscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LolLeagueSubscription>
 */
final class LolLeagueSubscriptionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'league' => LolLeague::Worlds,
            'mode' => LolSubscriptionMode::All,
            'team_codes' => null,
            'costream_channel' => null,
        ];
    }

    /**
     * @param  list<string>  $codes
     */
    public function teams(array $codes): static
    {
        return $this->state(fn (): array => [
            'mode' => LolSubscriptionMode::Teams,
            'team_codes' => $codes,
        ]);
    }
}

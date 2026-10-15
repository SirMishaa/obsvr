<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\LolLeague;
use App\Models\LolMatch;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LolMatch>
 */
final class LolMatchFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'id' => (string) fake()->unique()->numerify('11566058858######'),
            'league' => LolLeague::Worlds,
            'start_time' => now()->addHour(),
            'state' => 'unstarted',
            'block_name' => 'Play-Ins',
            'best_of' => 5,
            'teams' => [
                ['name' => 'Cloud9', 'code' => 'C9', 'image' => 'https://static.lolesports.com/teams/c9.png'],
                ['name' => 'Karmine Corp', 'code' => 'KC', 'image' => 'https://static.lolesports.com/teams/kc.png'],
            ],
        ];
    }

    public function startingAt(CarbonInterface $at): static
    {
        return $this->state(fn (): array => ['start_time' => $at]);
    }
}

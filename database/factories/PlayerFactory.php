<?php

namespace Database\Factories;

use App\Models\Player;
use App\Models\Team; // Import Team
use Illuminate\Database\Eloquent\Factories\Factory;

class PlayerFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Player::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate unique-ish jersey numbers for seeding purposes
        static $jerseyCounters = [];
        $teamId = $this->getTeamId(); // Helper to get team ID being assigned
        if (!isset($jerseyCounters[$teamId])) {
            $jerseyCounters[$teamId] = 0;
        }
        $jerseyNumber = ++$jerseyCounters[$teamId];


        return [
            'team_id' => Team::factory(), // Associate with a Team
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'jersey_number' => (string)$jerseyNumber, // Simple counter per team seed run
            'email' => fake()->unique()->safeEmail(), // Optional email
        ];
    }

    /**
     * Helper to safely get the team_id attribute if set by the seeder.
     */
    private function getTeamId(): int|string|null
    {
        // Access attributes passed to the factory state or relationship
         return $this->attributes['team_id'] ?? (isset($this->states['team_id']) ? $this->states['team_id'] : null);
    }
}

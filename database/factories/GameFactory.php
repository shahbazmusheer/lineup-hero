<?php

namespace Database\Factories;

use App\Models\Game;
use App\Models\Team; // Import Team
use Illuminate\Database\Eloquent\Factories\Factory;

class GameFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Game::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'opponent_name' => fake()->city() . ' ' . fake()->randomElement(['Giants', 'Dodgers', 'Yankees', 'Sox', 'Cubs']),
            'game_date' => fake()->dateTimeBetween('-3 months', '+3 months'),
            'innings' => fake()->randomElement([6, 7, 9]),
            'location_type' => fake()->randomElement(['home', 'away']),
            'lineup_data' => null, // No lineup initially
            'submitted_at' => null, // Not submitted initially
        ];
    }
}

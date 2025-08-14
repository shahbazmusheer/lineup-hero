<?php

namespace Database\Factories;

use App\Models\Team;
use App\Models\User; // Import User
use App\Models\Organization; // Import Organization
use Illuminate\Database\Eloquent\Factories\Factory;

class TeamFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Team::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $sport = fake()->randomElement(['baseball', 'softball']);
        $teamType = fake()->randomElement(['travel', 'recreation', 'school']);
        $ageGroup = fake()->randomElement(['8u','9u','10u','11u','12u','13u','14u','Varsity','JV']);
        $season = fake()->randomElement(['Spring', 'Summer', 'Fall', 'Winter']);

        return [
            // Use closure for User/Org to ensure they exist or are created
            'user_id' => User::factory(),
            'organization_id' => Organization::factory(),
            'name' => fake()->city() . ' ' . fake()->randomElement(['Raptors','Eagles','Tigers','Cobras','Warriors','Knights']),
            'season' => $season,
            'year' => fake()->numberBetween(now()->year - 1, now()->year + 1),
            'sport_type' => $sport,
            'team_type' => $teamType,
            'age_group' => $ageGroup,
            'city' => fake()->city(),
            'state' => fake()->stateAbbr(),
        ];
    }
}

<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Organization::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Generate a unique organization code
        $organizationCode = null;
        do {
            // Example: 6-character uppercase alphanumeric code
            $organizationCode = strtoupper(Str::random(6));
        } while (Organization::where('organization_code', $organizationCode)->exists());

        return [
            'name' => fake()->company() . ' ' . fake()->randomElement(['League', 'Association', 'Club']),
            'email' => fake()->unique()->safeEmail(),
            'organization_code' => $organizationCode, // Add the generated code
        ];
    }
}

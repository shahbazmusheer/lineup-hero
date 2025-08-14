<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            PositionSeeder::class,
            AdminSeeder::class,
            UserSeeder::class,
            SettingsSeeder::class,
            // Add other seeders here if needed
        ]);

//         $this->call([
//             OrganizationTeamPlayerSeeder::class,
//         ]);
    }
}

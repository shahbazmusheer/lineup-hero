<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\Organization;
use App\Models\Player;
use App\Models\Position;
use App\Models\Team;
use App\Models\User; // Make sure User model is imported
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
// No need for Hash here if not creating users directly in this seeder

class OrganizationTeamPlayerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // --- Configurable Options ---
        $numberOfOrganizationsToCreatePerUser = 2; // How many new orgs to create for each selected user's teams
        $teamsPerOrganization = 2;
        $playersPerTeam = 12;
        $gamesPerTeam = 3;
        $numberOfExistingUsersToProcess = 3; // How many existing users to create data for (e.g., the first 3)
        // Set to a high number or User::count() to process all.
        // --- End Options ---

        $this->command->info("Seeding data for existing Users: Organizations, Teams, Players, Preferences, and Games...");

        // --- Fetch Positions (Prerequisite) ---
        $positions = Position::all();
        $preferablePositions = $positions->whereNotIn('name', ['OUT', 'BENCH', 'DH', 'EH'])->pluck('id')->toArray();
        if (empty($preferablePositions)) {
            $this->command->error('No preferable positions found. Ensure PositionSeeder ran first.');
            return;
        }
        $this->command->info('Fetched Positions for preference setting.');

        // --- Fetch Existing Users ---
        // Get a collection of existing users. You can modify how you select them.
        // For example, get the first N users, or users with a specific role if you have roles.
        $existingUsers = User::orderBy('id', 'asc')->take($numberOfExistingUsersToProcess)->get();

        if ($existingUsers->isEmpty()) {
            $this->command->warn('No existing users found to seed data for. Ensure users are seeded first.');
            return;
        }
        $this->command->info("Found {$existingUsers->count()} existing users to process.");


        // --- Loop through each existing user and create their data ---
        foreach ($existingUsers as $currentUser) {
            $this->command->info("--- Processing for User: {$currentUser->email} (ID: {$currentUser->id}) ---");

            // Create Organizations. These could be global, or "owned" by users if your Org model has a user_id.
            // For this example, we'll create some new organizations and link the user's teams to them.
            // If organizations are truly global and shared, create them once outside this loop.
            $userOrganizations = Organization::factory()->count($numberOfOrganizationsToCreatePerUser)->create();
            $this->command->info("  Created {$numberOfOrganizationsToCreatePerUser} Organizations for User ID {$currentUser->id} context.");

            foreach ($userOrganizations as $org) {
                $this->command->info("  > Processing Organization: {$org->name} (ID: {$org->id}) for User ID {$currentUser->id}");

                // Create Teams for this Org, assigned to the CURRENT User
                $teams = Team::factory()
                    ->count($teamsPerOrganization)
                    ->for($currentUser) // Assign current existing user as owner
                    ->for($org)        // Assign to one of the newly created orgs
                    ->create();
                $this->command->info("    - Created {$teamsPerOrganization} Teams for User ID {$currentUser->id} in Org ID {$org->id}.");

                foreach ($teams as $team) {
                    $this->command->info("      - Processing Team: {$team->name} (ID: {$team->id})");
                    $players = Player::factory()->count($playersPerTeam)->state(['team_id' => $team->id])->create();
                    $this->command->info("        - Created {$playersPerTeam} Players.");

                    // Set Preferences for each Player
                    foreach ($players as $player) {
                        $numPreferred = rand(1, 3); $numRestricted = rand(0, 2);
                        $numPreferred = min($numPreferred, count($preferablePositions));
                        $numRestricted = min($numRestricted, count($preferablePositions) - $numPreferred);
                        $prefsToSync = [];

                        if (count($preferablePositions) >= $numPreferred) {
                            $preferredIds = ($numPreferred > 0) ? Arr::random($preferablePositions, $numPreferred) : [];
                            $preferredIds = is_array($preferredIds) ? $preferredIds : [$preferredIds];
                            foreach ($preferredIds as $id) { $prefsToSync[$id] = ['preference_type' => 'preferred']; }

                            $remainingPositions = array_diff($preferablePositions, $preferredIds);
                            if (count($remainingPositions) >= $numRestricted && $numRestricted > 0) {
                                $restrictedIds = Arr::random($remainingPositions, $numRestricted);
                                $restrictedIds = is_array($restrictedIds) ? $restrictedIds : [$restrictedIds];
                                foreach ($restrictedIds as $id) { $prefsToSync[$id] = ['preference_type' => 'restricted']; }
                            }

                            if (!empty($prefsToSync)) {
                                $player->belongsToMany(Position::class, 'player_position_preferences', 'player_id', 'position_id')
                                    ->withPivot('preference_type')->sync($prefsToSync);
                            } else {
                                $player->belongsToMany(Position::class, 'player_position_preferences', 'player_id', 'position_id')->detach();
                            }
                        }
                    }
                    $this->command->info("        - Set random Player Preferences.");

                    // Create Games for this Team
                    Game::factory()->count($gamesPerTeam)->for($team)->create();
                    $this->command->info("        - Created {$gamesPerTeam} Games.");
                } // End team loop
            } // End organization loop for this user
        } // End loop for existing users

        $this->command->info('Database seeding for existing users completed successfully!');
    }
}
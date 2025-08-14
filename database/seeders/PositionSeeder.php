<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Position;

class PositionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $positions = [
            // As per Figma mapping (Display Name = Short Code)
            ['name' => 'P',  'display_name' => 'Pitcher',        'category' => 'PITCHER', 'is_editable' => false],
            ['name' => 'C',  'display_name' => 'Catcher',        'category' => 'CATCHER', 'is_editable' => false],
            ['name' => '1B', 'display_name' => '1st Base',       'category' => 'INF',     'is_editable' => false],
            ['name' => '2B', 'display_name' => '2nd Base',       'category' => 'INF',     'is_editable' => false],
            ['name' => '3B', 'display_name' => '3rd Base',       'category' => 'INF',     'is_editable' => false],
            ['name' => 'SS', 'display_name' => 'Short Stop',     'category' => 'INF',     'is_editable' => false],
            ['name' => 'LF', 'display_name' => 'Left Field',     'category' => 'OF',      'is_editable' => false],
            ['name' => 'RF', 'display_name' => 'Right Field',    'category' => 'OF',      'is_editable' => false],
            ['name' => 'CF', 'display_name' => 'Center Field',   'category' => 'OF',      'is_editable' => false],

            // "Bench = OUT" implies 'OUT' is the representation for benched players in the grid.
            // We will have a single 'OUT' record for this.
            // If you need a separate 'BENCH' concept for players not dressed / fully out of game
            // vs. 'OUT' for an inning, you could add 'BENCH' back.
            // For now, 'OUT' covers the Figma "Bench = OUT".
            ['name' => 'OUT',   'display_name' => 'Bench',  'category' => 'SPECIAL', 'is_editable' => false],

            // Removed based on previous request. Add back if client clarifies need for DH/EH as editable custom positions.
            // ['name' => 'DH',    'display_name' => 'Des. Hitter',    'category' => 'SPECIAL', 'is_editable' => true],
            // ['name' => 'EH',    'display_name' => 'Extra Hitter',   'category' => 'SPECIAL', 'is_editable' => true],
        ];

        $this->command->info('Seeding/Updating Positions according to Figma specification...');
        $count = 0;
        $currentPositionNamesInSeeder = array_map(fn($p) => $p['name'], $positions);

        // Optional: Delete positions from DB that are NOT in the $positions array above
        // This ensures the DB exactly matches this seeder list after running.
        // Only run this in development if you want to strictly enforce this list.
        if (app()->environment(['local', 'development'])) {
            // Get names of core, non-editable positions that should NEVER be deleted
            $protectedNames = [];
            foreach($positions as $p) {
                if ($p['is_editable'] === false) {
                    $protectedNames[] = $p['name'];
                }
            }
            // Ensure we only delete positions not in our current seeder list AND are not core protected ones (if any were missed)
            $positionsToDelete = Position::whereNotIn('name', $currentPositionNamesInSeeder)
                // ->whereNotIn('name', $someOtherListOfProtectedPositionsIfAny) // If more protection needed
                ->get();
            $deletedCount = 0;
            foreach($positionsToDelete as $posToDelete) {
                // Add extra safety: only delete if it was marked as editable, or handle differently
                if($posToDelete->is_editable) {
                    $posToDelete->delete();
                    $deletedCount++;
                } else {
                    $this->command->warn("Skipped deleting non-editable position '{$posToDelete->name}' not in seeder. Review manually.");
                }
            }

            if ($deletedCount > 0) {
                $this->command->warn("Deleted {$deletedCount} editable positions not in the current seeder list.");
            }
        }


        foreach ($positions as $position) {
            Position::updateOrCreate(
                ['name' => $position['name']], // Find by unique short code name
                [                              // Attributes to set/update
                    'display_name' => $position['display_name'],
                    'category' => $position['category'],
                    'is_editable' => $position['is_editable'],
                ]
            );
            $count++;
        }
        $this->command->info("Processed {$count} positions.");
    }
}
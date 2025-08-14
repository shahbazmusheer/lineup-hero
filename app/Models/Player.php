<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class Player extends Model
{
    use HasFactory;
    protected $fillable = ['team_id', 'first_name', 'last_name', 'jersey_number', 'email', 'phone'];
    protected ?array $calculatedStatsCache = null;
    protected $appends = ['stats', 'full_name'];

    // --- Relationships ---
    public function team() { return $this->belongsTo(Team::class); }
    public function positionPreferences() { return $this->hasMany(PlayerPositionPreference::class); }
    public function preferredPositions() {
        return $this->belongsToMany(Position::class, 'player_position_preferences', 'player_id', 'position_id')
            ->wherePivot('preference_type', 'preferred');
    }
    public function restrictedPositions() {
        return $this->belongsToMany(Position::class, 'player_position_preferences', 'player_id', 'position_id')
            ->wherePivot('preference_type', 'restricted');
    }

    // --- Accessors ---

    /**
     * The main stats accessor.
     * It calls the original, less performant method for backward compatibility.
     */
    protected function stats(): Attribute
    {
        return Attribute::make(get: fn () => $this->calculateHistoricalStats());
    }

    protected function fullName(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => ($attributes['first_name'] ?? '') . ' ' . ($attributes['last_name'] ?? '')
        );
    }

    // --- Stats Calculation ---

    /**
     * PUBLIC METHOD 1: The original N+1 query pattern.
     * This method is kept for any part of your app that might use it directly.
     * It now simply fetches data and DELEGATES to the private helper.
     */
    public function calculateHistoricalStats(): array
    {
        if ($this->calculatedStatsCache !== null) return $this->calculatedStatsCache;

        $this->loadMissing('team');
        if (!$this->team) {
            Log::warning("Player ID {$this->id} missing team for stats.");
            return $this->cacheStatsResult($this->getDefaultStatsStructure());
        }

        // This is the database query that causes the N+1 problem.
        $submittedGames = $this->team->games()
            ->whereNotNull('submitted_at')->whereNotNull('lineup_data')
            ->where(fn($q) => $q->where('lineup_data', '!=', '[]')->where('lineup_data', '!=', '{}'))
            ->get(['id', 'innings', 'lineup_data']);

        // DELEGATE the calculation and cache the result.
        $result = $this->_performStatsCalculation($submittedGames);
        return $this->cacheStatsResult($result);
    }

    /**
     * PUBLIC METHOD 2: The new, optimized method for the autocomplete controller.
     * This method does NOT query the DB; it uses pre-loaded data.
     */
    public function calculateHistoricalStatsFromLoadedGames(): array
    {
        if (!$this->relationLoaded('team') || !$this->team->relationLoaded('games')) {
            Log::warning("Attempted to calculate stats from non-loaded games for Player ID {$this->id}. Falling back.");
            // Fallback to the original method if data wasn't eager-loaded.
            return $this->calculateHistoricalStats();
        }

        // Use the eager-loaded collection and DELEGATE to the private helper.
        return $this->_performStatsCalculation($this->team->games);
    }

    // --- Private Helper Methods ---

    /**
     * PRIVATE HELPER: The single source of truth for all stats calculations.
     * This method contains the CORRECT logic that counts "OUT" positions.
     */
    private function _performStatsCalculation(Collection $submittedGames): array
    {
        if ($submittedGames->isEmpty()) {
            return $this->getDefaultStatsStructure();
        }

        // This part remains the same...
        static $infPositions = null, $ofPositions = null;
        if ($infPositions === null) {
            $allPositions = Position::select('name', 'category')->get()->keyBy('name');
            $infPositions = $allPositions->where('category', 'INF')->keys()->map(fn($name) => strtoupper($name))->all();
            $ofPositions = $allPositions->where('category', 'OF')->keys()->map(fn($name) => strtoupper($name))->all();
        }

        $totalGameInningsAvailable = 0;
        $playerActiveInnings = 0;
        $positionCounts = [];
        $battingLocations = [];
        $infInningsPlayed = 0;
        $ofInningsPlayed = 0;

        // This loop that calculates the counts is correct and does not need to change.
        foreach ($submittedGames as $game) {
            try {
                $lineupData = $game->lineup_data;
                if (empty($lineupData)) continue;

                $lineupCollection = collect(is_array($lineupData) ? $lineupData : json_decode($lineupData, true));
                $playerLineupEntry = $lineupCollection->firstWhere('player_id', $this->id);

                if ($playerLineupEntry) {
                    $totalGameInningsAvailable += $game->innings;
                    if (isset($playerLineupEntry['innings']) && (is_array($playerLineupEntry['innings']) || is_object($playerLineupEntry['innings']))) {
                        foreach ((array) $playerLineupEntry['innings'] as $position) {
                            if (!empty($position) && is_string($position)) {
                                $positionCounts[$position] = ($positionCounts[$position] ?? 0) + 1;
                                $upperPos = strtoupper($position);
                                if ($upperPos !== 'OUT' && $upperPos !== 'BENCH') {
                                    $playerActiveInnings++;
                                    if (in_array($upperPos, $infPositions)) $infInningsPlayed++;
                                    if (in_array($upperPos, $ofPositions)) $ofInningsPlayed++;
                                }
                            }
                        }
                    }
                    if (isset($playerLineupEntry['batting_order']) && is_numeric($playerLineupEntry['batting_order'])) {
                        $battingLocations[] = (int) $playerLineupEntry['batting_order'];
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Stats Calc Err: Game {$game->id}, Player {$this->id}: " . $e->getMessage());
                continue;
            }
        }

        $pctInningsPlayed = ($totalGameInningsAvailable > 0) ? round(($playerActiveInnings / $totalGameInningsAvailable) * 100, 1) : 0.0;

        // --- MODIFICATION START ---
        // Calculate top_position from a filtered list that EXCLUDES 'OUT' and 'BENCH'.
        $topPosition = null;
        // 1. Create a temporary copy of the position counts.
        $onFieldPositions = $positionCounts;
        // 2. Remove the keys we don't want to consider for 'top_position'.
        unset($onFieldPositions['OUT'], $onFieldPositions['BENCH']);
        // 3. Find the top position only from the remaining on-field positions.
        if (!empty($onFieldPositions)) {
            arsort($onFieldPositions); // Sort by count descending
            $topPosition = key($onFieldPositions); // Get the key of the highest value
        }
        // --- MODIFICATION END ---

        $avgBattingLoc = !empty($battingLocations) ? (int) round(array_sum($battingLocations) / count($battingLocations)) : null;
        $pctInfPlayed = ($playerActiveInnings > 0) ? round(($infInningsPlayed / $playerActiveInnings) * 100, 1) : 0.0;
        $pctOfPlayed = ($playerActiveInnings > 0) ? round(($ofInningsPlayed / $playerActiveInnings) * 100, 1) : 0.0;

        return [
            'pct_innings_played' => $pctInningsPlayed,
            'top_position' => $topPosition, // This is now correctly calculated
            'avg_batting_loc' => $avgBattingLoc,
            // The original, unmodified $positionCounts is returned here, so it still contains "OUT".
            'position_counts' => !empty($positionCounts) ? $positionCounts : (object) [],
            'total_innings_participated_in' => $totalGameInningsAvailable,
            'active_innings_played' => $playerActiveInnings,
            'pct_inf_played' => $pctInfPlayed,
            'pct_of_played' => $pctOfPlayed,
        ];
    }

    private function getDefaultStatsStructure(): array
    {
        return [
            'pct_innings_played' => 0.0, 'top_position' => null, 'avg_batting_loc' => null,
            'position_counts' => (object) [], 'total_innings_participated_in' => 0,
            'active_innings_played' => 0, 'pct_inf_played' => 0.0, 'pct_of_played' => 0.0,
        ];
    }

    private function cacheStatsResult(array $result): array
    {
        $this->calculatedStatsCache = $result;
        return $result;
    }
}
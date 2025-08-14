<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait; // <-- USE TRAIT
use App\Models\Player;
use App\Models\Position;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class PlayerPreferenceController extends Controller
{
    use ApiResponseTrait; // <-- INCLUDE TRAIT

    public function show(Request $request, Player $player)
    {
        if (!$player->team || $request->user()->id !== $player->team->user_id) {
            return $this->forbiddenResponse('You do not manage this player.');
        }

        $player->load(['preferredPositions:id,name', 'restrictedPositions:id,name']);
        $responseData = [
            'player_id' => $player->id,
            // Pluck IDs, or client can map from full objects if preferred
            'preferred_positions' => $player->preferredPositions->pluck('id'),
            'restricted_positions' => $player->restrictedPositions->pluck('id'),
        ];
        return $this->successResponse($responseData, 'Player preferences retrieved.');
    }

    public function store(Request $request, Player $player)
    {
        if (!$player->team || $request->user()->id !== $player->team->user_id) {
            return $this->forbiddenResponse('You do not manage this player.');
        }

        $validator = Validator::make($request->all(), [
            'preferred_position_ids' => 'present|array',
            'preferred_position_ids.*' => 'integer|exists:positions,id',
            'restricted_position_ids' => 'present|array',
            'restricted_position_ids.*' => 'integer|exists:positions,id',
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $preferredIds = $request->input('preferred_position_ids', []);
        $restrictedIds = $request->input('restricted_position_ids', []);

        if (!empty(array_intersect($preferredIds, $restrictedIds))) {
            return $this->errorResponse('A position cannot be both preferred and restricted.', Response::HTTP_UNPROCESSABLE_ENTITY, ['conflicting_ids' => array_intersect($preferredIds, $restrictedIds)]);
        }

        $outPosition = Position::where('name', 'OUT')->first();
        if ($outPosition && (in_array($outPosition->id, $preferredIds) || in_array($outPosition->id, $restrictedIds))) {
            return $this->errorResponse('The "OUT" position cannot be set as a preference.', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            DB::transaction(function () use ($player, $preferredIds, $restrictedIds) {
                $prefsToSync = [];
                foreach ($preferredIds as $id) { $prefsToSync[$id] = ['preference_type' => 'preferred']; }
                foreach ($restrictedIds as $id) { $prefsToSync[$id] = ['preference_type' => 'restricted']; }

                $player->belongsToMany(Position::class, 'player_position_preferences', 'player_id', 'position_id')
                    ->withPivot('preference_type')->sync($prefsToSync);
            });

            $player->load(['preferredPositions:id,name', 'restrictedPositions:id,name']);
            $responseData = [
                'player_id' => $player->id,
                'preferred_positions' => $player->preferredPositions->pluck('id'),
                'restricted_positions' => $player->restrictedPositions->pluck('id'),
            ];
            return $this->successResponse($responseData, 'Player preferences updated successfully.');

        } catch (\Exception $e) {
            Log::error("Error updating player preferences for Player ID {$player->id}: " . $e->getMessage());
            return $this->errorResponse('Failed to update preferences.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Update preferences for multiple players on a specific team in bulk.
     * Route: PUT /teams/{team}/bulk-player-preferences
     */
    public function bulkUpdateByTeam(Request $request, Team $team)
    {
        $user = $request->user();

        // Authorization: Ensure user owns the team
        if ($user->id !== $team->user_id) {
            return $this->forbiddenResponse('You do not own this team.');
        }


//        return response()->json(['message' => 'Player preferences updated successfully.'], Response::HTTP_OK);
        // --- Validate the incoming payload structure ---
        // Expected structure:
        // {
        //   "player_preferences": [
        //     { "player_id": 1, "preferred_position_ids": [1,2], "restricted_position_ids": [3] },
        //     { "player_id": 2, "preferred_position_ids": [], "restricted_position_ids": [4,5] },
        //     ...
        //   ]
        // }
        $validator = Validator::make($request->all(), [
            'player_preferences' => 'required|array|min:1',
            'player_preferences.*.player_id' => [
                'required',
                'integer',
                // Ensure player_id exists and belongs to the specified team
                Rule::exists('players', 'id')->where(function ($query) use ($team) {
                    $query->where('team_id', $team->id);
                }),
            ],
            'player_preferences.*.preferred_position_ids' => 'present|array',
            'player_preferences.*.preferred_position_ids.*' => 'integer|exists:positions,id',
            'player_preferences.*.restricted_position_ids' => 'present|array',
            'player_preferences.*.restricted_position_ids.*' => 'integer|exists:positions,id',
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }


        $allPlayerPreferencesInput = $request->input('player_preferences');
        $outPositionId = Position::where('name', 'OUT')->value('id'); // Get ID of 'OUT' position

        // --- Further validation for each player's preferences ---
        foreach ($allPlayerPreferencesInput as $index => $prefsInput) {
            $preferredIds = $prefsInput['preferred_position_ids'] ?? [];
            $restrictedIds = $prefsInput['restricted_position_ids'] ?? [];

            // 1. Check for overlap between preferred and restricted for a single player
            if (!empty(array_intersect($preferredIds, $restrictedIds))) {
                return $this->errorResponse(
                    "Player ID {$prefsInput['player_id']}: A position cannot be both preferred and restricted.",
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['field_index' => $index] // Helps client identify which entry failed
                );
            }
            // 2. Check if 'OUT' position is being set as preferred or restricted
            if ($outPositionId && (in_array($outPositionId, $preferredIds) || in_array($outPositionId, $restrictedIds))) {
                return $this->errorResponse(
                    "Player ID {$prefsInput['player_id']}: The 'OUT' position cannot be set as a preference.",
                    Response::HTTP_UNPROCESSABLE_ENTITY,
                    ['field_index' => $index]
                );
            }
        }

        // --- Process Updates within a Transaction ---
        DB::beginTransaction();
        try {
            foreach ($allPlayerPreferencesInput as $prefsInput) {
                $player = Player::find($prefsInput['player_id']); // Player existence already validated
                if (!$player) continue; // Should not happen due to Rule::exists

                $preferredIds = $prefsInput['preferred_position_ids'] ?? [];
                $restrictedIds = $prefsInput['restricted_position_ids'] ?? [];

                $prefsToSync = [];
                foreach ($preferredIds as $id) { $prefsToSync[$id] = ['preference_type' => 'preferred']; }
                foreach ($restrictedIds as $id) { $prefsToSync[$id] = ['preference_type' => 'restricted']; }

                // Sync method efficiently handles attaching, detaching, and updating pivot data
                $player->belongsToMany(Position::class, 'player_position_preferences', 'player_id', 'position_id')
                    ->withPivot('preference_type') // Important for sync to work with pivot data correctly
                    ->sync($prefsToSync);
            }

            DB::commit();
            Log::info("Bulk player preferences updated for Team ID: {$team->id} by User ID: {$user->id}");
            return $this->successResponse(null, 'Player preferences updated successfully for the team.', Response::HTTP_OK, false);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Bulk player preference update failed for Team ID {$team->id}: " . $e->getMessage(), ['exception' => $e]);
            return $this->errorResponse('Failed to update player preferences due to an internal error.', Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get preferences for all players on a specific team.
     * Route: GET /teams/{team}/bulk-player-preferences
     */
    public function bulkShowByTeam(Request $request, Team $team)
    {
        $user = $request->user();

        // Authorization: Ensure user owns the team
        if ($user->id !== $team->user_id) {
            return $this->forbiddenResponse('You do not own this team.');
        }

        // Eager load players and their preferences for efficiency
        $playersWithPreferences = $team->players()
            ->with(['preferredPositions:id,name', 'restrictedPositions:id,name']) // Eager load only id and name of positions
            ->select(['id', 'team_id', 'first_name', 'last_name']) // Select only necessary player fields
            ->orderBy('last_name') // Optional: Order players
            ->orderBy('first_name')
            ->get();

        $formattedPreferences = $playersWithPreferences->map(function ($player) {
            return [
                'player_id' => $player->id,
//                'first_name' => $player->first_name, // Optional: include for easier UI mapping
//                'last_name' => $player->last_name,   // Optional: include for easier UI mapping
                'preferred_position_ids' => $player->preferredPositions->pluck('id')->toArray(),
                'restricted_position_ids' => $player->restrictedPositions->pluck('id')->toArray(),
            ];
        });

        return $this->successResponse(
            ['player_preferences' => $formattedPreferences], // Wrap in a key
            'Player preferences for team retrieved successfully.'
        );
    }
}

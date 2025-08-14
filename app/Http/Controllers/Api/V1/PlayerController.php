<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponseTrait; // <-- USE TRAIT
use App\Models\Player;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Http\Response; // For status codes

class PlayerController extends Controller
{
    use ApiResponseTrait; // <-- INCLUDE TRAIT

    public function store(Request $request, Team $team)
    {
        if ($request->user()->id !== $team->user_id) {
            return $this->forbiddenResponse('You do not own this team.');
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'jersey_number' => [
                'nullable',
                'string',
                'max:10',
                Rule::unique('players', 'jersey_number')->where(function ($query) use ($team) {
                    return $query->where('team_id', $team->id);
                }),
            ],
            'email' => ['nullable','email','max:255', Rule::unique('players', 'email')],
            'phone' => ['nullable','string','max:20', Rule::unique('players', 'phone')],
        ]);
        if ($validator->fails()) return $this->validationErrorResponse($validator);

        $player = $team->players()->create($validator->validated());
        return $this->successResponse($player, 'Player created successfully.', Response::HTTP_CREATED);
    }

    public function show(Request $request, Player $player)
    {
        if (!$player->team || $request->user()->id !== $player->team->user_id && !$request->user('api_admin')) {
            return $this->forbiddenResponse('You cannot access this player.');
        }
        $player->load(['team:id,name', 'preferredPositions:id,name', 'restrictedPositions:id,name']);
        return $this->successResponse($player);
    }

    public function update(Request $request, Player $player)
    {
        // Authorization: Ensure user owns the team this player belongs to
        if (!$player->team || $request->user()->id !== $player->team->user_id) {
            return $this->forbiddenResponse('You do not manage this player.');
        }

        $validator = Validator::make($request->all(), [
            'first_name' => 'sometimes|required|string|max:100',
            'last_name' => 'sometimes|required|string|max:100',
            'jersey_number' => [
                'sometimes', // Only validate if present
                'nullable',  // Allow setting to null
                'string',
                'max:10',
                Rule::unique('players', 'jersey_number') // Check in 'players' table, 'jersey_number' column
                ->where(function ($query) use ($player) {
                    return $query->where('team_id', $player->team_id); // Scope uniqueness to the player's current team
                })
                    ->ignore($player->id) // Ignore the current player being updated
            ],
            'email' => [
                'sometimes',
                'nullable',
                'email',
                'max:255',
                Rule::unique('players', 'email')->ignore($player->id), // Optional soft delete check
            ],
            'phone' => [
                'sometimes',
                'nullable',
                'string',
                'max:20',
                Rule::unique('players', 'phone')->ignore($player->id), // Optional soft delete check
            ],
        ]);

        if ($validator->fails()) {
            return $this->validationErrorResponse($validator);
        }

        $validatedData = $validator->validated();

        // Handle cases where an empty string for nullable fields should be converted to null
        foreach (['jersey_number', 'email', 'phone'] as $field) {
            if (array_key_exists($field, $validatedData) && $validatedData[$field] === '') {
                $validatedData[$field] = null;
            }
        }

        $player->update($validatedData);
        return $this->successResponse($player, 'Player updated successfully.');
    }

    public function destroy(Request $request, Player $player)
    {
        if (!$player->team || $request->user()->id !== $player->team->user_id) {
            return $this->forbiddenResponse('You do not manage this player.');
        }
        $player->delete();
        return $this->successResponse(null, 'Player deleted successfully.', Response::HTTP_OK, false);
    }
}

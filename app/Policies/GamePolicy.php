<?php

namespace App\Policies;

use App\Models\Game;
use App\Models\User;
use App\Models\Team; // Import Team
use Illuminate\Auth\Access\HandlesAuthorization;

class GamePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any games for a specific team.
     */
    public function viewAny(User $user, Team $team): bool
    {
        return $user->id === $team->user_id;
    }

    /**
     * Determine whether the user can view the game.
     */
    public function view(User $user, Game $game): bool
    {
        return $user->id === $game->team->user_id;
    }

    /**
     * Determine whether the user can create a game for a specific team.
     */
    public function create(User $user, Team $team): bool
    {
        return $user->id === $team->user_id && $team->isEditable(); // Check if team is editable
    }

    /**
     * Determine whether the user can update the game.
     */
    public function update(User $user, Game $game): bool
    {
        return $user->id === $game->team->user_id && $game->team->isEditable(); // Check if team is editable
    }

    /**
     * Determine whether the user can delete the game.
     */
    public function delete(User $user, Game $game): bool
    {
        return $user->id === $game->team->user_id && $game->team->isEditable(); // Check if team is editable
    }

    /**
     * Determine whether the user can get PDF data for the game.
     * Access depends on the team's premium status (either direct or via organization).
     */
    public function viewPdfData(User $user, Game $game): bool
    {
        if ($user->id !== $game->team->user_id) {
            return false; // Must own the team
        }
        // The Team model's hasPremiumAccess() method implements the dual check
//        return $game->team->hasPremiumAccess();
        return true;
    }
}
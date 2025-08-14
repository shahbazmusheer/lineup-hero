<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class TeamPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     * (User can list their own teams)
     */
    public function viewAny(User $user): bool
    {
        return true; // Any authenticated user can attempt to list their teams
    }

    /**
     * Determine whether the user can view the model.
     * (User can view their own team)
     */
    public function view(User $user, Team $team): bool
    {
        return $user->id === $team->user_id;
    }

    /**
     * Determine whether the user can create models.
     * (Any authenticated user can attempt to create a team,
     * further logic in controller checks for slots/org status)
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the model.
     * User must own the team AND the team must be within its editable period.
     */
    public function update(User $user, Team $team): bool {
        return $user->id === $team->user_id && $team->isEditable(); // Uses new logic
    }

    /**
     * Determine whether the user can delete the model.
     * User must own the team AND the team must be within its editable period.
     */
    public function delete(User $user, Team $team): bool {
        return $user->id === $team->user_id && $team->isEditable(); // Uses new logic
    }

    // Add canUsePremiumFeature ability if needed for optimizer etc.
    // public function usePremiumFeature(User $user, Team $team): bool {
    //     return $user->id === $team->user_id && $team->hasPremiumFeatureAccess();
    // }
    // Add restore and forceDelete if using soft deletes
}
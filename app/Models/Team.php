<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\Settings;
use App\Models\UserTeamActivationSlot;

class Team extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'organization_id', 'name', 'sport_type', 'team_type', 'age_group',
        'city', 'state', 'country', 'year', 'season',
        'direct_activation_status', 'direct_activation_expires_at',
        'is_editable_until', 'is_setup_complete'
    ];

    protected $casts = [
        'year' => 'integer',
        'direct_activation_expires_at' => 'datetime',
        'is_editable_until' => 'datetime',
        'is_setup_complete' => 'boolean'
    ];

    // --- Relationships ---

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }

    public function players()
    {
        return $this->hasMany(Player::class);
    }

    public function games()
    {
        return $this->hasMany(Game::class);
    }

    public function directPayments() { return $this->morphMany(Payment::class, 'payable'); }
    public function directPromoRedemptions() { return $this->morphMany(PromoCodeRedemption::class, 'redeemable'); }

    /**
     * Determines if the team is currently editable.
     * - Independent teams (Path A): Based on their direct_activation_expires_at.
     * - Organizational teams (Path B): Based on their parent Organization's subscription_expires_at.
     * - All teams also have an initial 1-year is_editable_until from creation.
     *   Editability is true if BOTH its own initial window is valid AND its activation path is valid.
     */
    public function isEditable(): bool
    {

        // 2. Check activation path
        if ($this->organization_id && $this->organization) {
            // Path B: Linked to an Organization
            $this->loadMissing('organization');
            return $this->organization->hasActiveSubscription(); // True if org sub is active & not expired
        } elseif (!$this->organization_id) {
            // Path A: Independent Team
            return $this->direct_activation_status === 'active' &&
                $this->direct_activation_expires_at &&
                $this->direct_activation_expires_at->isFuture();
        }

        // 1. Check the team's own 'is_editable_until' (1 year from creation)
//        if (!$this->is_editable_until || $this->is_editable_until->isPast()) {
//            return false; // Initial editability window has passed
//        }
        return false; // Default to not editable if no clear path
    }

    /**
     * Determines if the team has access to premium features (which might be different from editability).
     * For now, let's assume premium access follows the same logic as editability for simplicity,
     * but this can be made distinct if needed. PDF access is always true for owner.
     */
    public function hasPremiumFeatureAccess(): bool
    {
        // This logic might differ from isEditable() if "premium features"
        // are defined differently from "editability".
        // For now, let's mirror isEditable but without the team's own is_editable_until constraint.
        // The primary driver is the activation status (direct or via org).

        if ($this->organization_id && $this->organization) {
            // Path B: Linked to an Organization
            $this->loadMissing('organization');
            return $this->organization->hasActiveSubscription();
        } elseif (!$this->organization_id) {
            // Path A: Independent Team
            return $this->direct_activation_status === 'active' &&
                $this->direct_activation_expires_at &&
                $this->direct_activation_expires_at->isFuture();
        }
        return false;
    }

    /**
     * Activates this team directly (Path A) using a pre-paid/promo slot or direct payment.
     * Also extends its editability window.
     */
    public function activateDirectly(Carbon $newActivationExpiry): void
    {
        $this->direct_activation_status = 'active';
        $this->direct_activation_expires_at = $newActivationExpiry;

        // Extend editability to match the new activation expiry, if it's later
        if (!$this->is_editable_until || $newActivationExpiry->gt($this->is_editable_until)) {
            $this->is_editable_until = $newActivationExpiry;
        }
        // Ensure is_setup_complete is true if being activated
        // $this->is_setup_complete = true; // Or handle this separately
        $this->save();
    }

    /**
     * Activates this team directly using a pre-paid/promo slot.
     * Consumes a UserTeamActivationSlot.
     */
    public function activateWithSlot(UserTeamActivationSlot $slot): bool
    {
        if ($slot->user_id !== $this->user_id || $slot->status !== 'available' || $slot->slot_expires_at->isPast()) {
            return false; // Slot not valid for this team/user or expired
        }

        $this->direct_activation_status = 'active';
        $this->direct_activation_expires_at = $slot->slot_expires_at;
        $this->is_setup_complete = true; // Assuming details are provided now or will be
        if (!$this->is_editable_until) { // Set initial editability window if not already set
            $this->is_editable_until = Carbon::now()->addYear();
        }
        $this->save();

        // Mark the slot as used and link it to this team
        $slot->status = 'used';
        $slot->team_id = $this->id;
        $slot->save();

        return true;
    }

}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable; // For login
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Carbon\Carbon;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Hash; // For password hashing

class Organization extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name', 'email', 'organization_code', 'password', 'pricing_type', 'custom_price_amount', 'creator_user_id',
        'subscription_status', 'subscription_expires_at',
        'stripe_customer_id', 'stripe_subscription_id',
        'annual_team_allocation', 'teams_created_this_period'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token', // Default hidden attribute
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'subscription_expires_at' => 'datetime',
        'password' => 'hashed',
        'annual_team_allocation' => 'integer',
        'teams_created_this_period' => 'integer',
        'custom_price_amount' => 'decimal:2'
    ];

    /**
     * Get the applicable annual subscription price for this organization in DOLLARS.
     * Returns custom price if set, otherwise falls back to global setting.
     */
    public function getSubscriptionPrice(): float
    {
        if ($this->pricing_type === 'custom' && $this->custom_price_amount > 0) {
            return (float) $this->custom_price_amount;
        }
        // Fallback to global price from settings
        return (float) Settings::instance()->unlock_price_amount;
    }

    /**
     * Get the user who created this organization.
     */
    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_user_id');
    }

    /**
     * Get the teams belonging to this organization.
     */
    public function teams()
    {
        return $this->hasMany(Team::class);
    }

    /**
     * Get the payments associated with this organization's subscription.
     */
    public function payments() { return $this->morphMany(Payment::class, 'payable'); }
    public function promoCodeRedemptions() { return $this->morphMany(PromoCodeRedemption::class, 'redeemable'); }

    // --- JWTSubject Methods ---
    public function getJWTIdentifier()
    {
        return $this->getKey(); // Returns the ID
    }

    public function getJWTCustomClaims()
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'organization_code' => $this->organization_code,
            'type' => 'organization_admin', // Custom claim to identify type
            'creator_user_id' => $this->creator_user_id,
            'has_active_subscription' => $this->hasActiveSubscription(),
            'subscription_expires_at' => $this->subscription_expires_at?->toISOString(),
        ];
    }

    // --- Subscription Logic ---
    public function hasActiveSubscription(): bool
    {
        if ($this->subscription_status !== 'active') {
            return false;
        }
        if ($this->subscription_expires_at && $this->subscription_expires_at->isPast()) {
            // Optional: Add logic to automatically set status to 'past_due' or 'inactive'
            return false;
        }
        return true;
    }

    public function canCreateMoreTeams(): bool {
        return $this->hasActiveSubscription() && ($this->teams_created_this_period < $this->annual_team_allocation);
    }
    public function grantSubscriptionAccess(Carbon $expiry, ?string $stripeSubId = null, ?string $stripeCustId = null, ?int $teamAllocation = null) {
        $this->subscription_status = 'active';
        $this->subscription_expires_at = $expiry;
        if ($stripeSubId) $this->stripe_subscription_id = $stripeSubId;
        if ($stripeCustId) $this->stripe_customer_id = $stripeCustId; // Org's own Stripe Customer
        if ($teamAllocation !== null) $this->annual_team_allocation = $teamAllocation;
        $this->teams_created_this_period = 0; // Reset on new subscription/renewal
        $this->save();
    }

    public function revokeSubscriptionAccess(string $newStatus = 'inactive'): void
    {
        $this->subscription_status = $newStatus;
        // Consider if expiry or password should be nulled out on revoke
        $this->save();
    }
}
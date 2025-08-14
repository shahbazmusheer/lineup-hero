<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Tymon\JWTAuth\Contracts\JWTSubject;
use Illuminate\Database\Eloquent\Casts\Attribute;
use App\Models\UserTeamActivationSlot;

class User extends Authenticatable implements JWTSubject
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'password', 'phone',
        'receive_payment_notifications','stripe_customer_id'
        // Subscription fields REMOVED from here
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'receive_payment_notifications' => 'boolean',
        // Subscription casts REMOVED
    ];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = ['full_name', 'available_team_slots_count'];

    // JWT Methods
    public function getJWTIdentifier() { return $this->getKey(); }
    public function getJWTCustomClaims() {
        return [
            'id' => $this->id, 'first_name' => $this->first_name, 'last_name' => $this->last_name,
            'email' => $this->email, 'phone' => $this->phone, 'type' => 'user',
            'role_id' => $this->role_id ?? 2, // Assuming role_id for general users
            'available_team_slots_count_claim' => $this->getAvailableTeamSlotsCountAttribute(),
            // 'has_active_subscription' is no longer a direct user attribute
        ];
    }

    // Relationships
    public function teams() { return $this->hasMany(Team::class); }

    /**
     * Organizations this user has created/paid for (acts as Org Admin).
     */
    public function administeredOrganizations()
    {
        return $this->hasMany(Organization::class, 'creator_user_id');
    }

    /**
     * Accessor for available_team_slots_count.
     * Name must be get[CamelCaseAttributeName]Attribute for Laravel to find it for $appends.
     *
     * @return int
     */
    public function getAvailableTeamSlotsCountAttribute(): int
    {
        // Ensure the relationship is loaded or count it directly
        if ($this->relationLoaded('teamActivationSlots')) {
            return $this->teamActivationSlots
                ->where('status', 'available')
                ->where('slot_expires_at', '>', now())
                ->count();
        }
        // If not loaded, query it
        return $this->teamActivationSlots()
            ->where('status', 'available')
            ->where('slot_expires_at', '>', now())
            ->count();
    }

    public function teamActivationSlots() { return $this->hasMany(UserTeamActivationSlot::class); }

    // Promo code redemptions and payments are now primarily linked to organizations,
    // but a user still performs the action.
    public function promoCodeRedemptions()
    {
        return $this->hasMany(PromoCodeRedemption::class); // User who redeemed
    }
    public function payments()
    {
        return $this->hasMany(Payment::class); // User who paid
    }

    // User-level subscription methods REMOVED
    // public function hasActiveSubscription(): bool { /* ... */ }
    // public function grantSubscriptionAccess(Carbon $expiresAt, /*...*/) { /* ... */ }
    // public function revokeSubscriptionAccess(string $newStatus = 'inactive'): void { /* ... */ }


    protected function fullName(): Attribute {
        return Attribute::make(get: fn ($v, $attr) => ($attr['first_name'] ?? '') . ' ' . ($attr['last_name'] ?? ''));
    }
}
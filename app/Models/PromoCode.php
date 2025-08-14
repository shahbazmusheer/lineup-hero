<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder; // Import Builder

class PromoCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'description',
        'expires_at',
        'max_uses',
        // 'use_count' should generally not be mass assignable
        'max_uses_per_user',
        'is_active',
        // 'duration_in_days',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'max_uses' => 'integer',
        'use_count' => 'integer',
        'max_uses_per_user' => 'integer',
        'is_active' => 'boolean',
        // 'duration_in_days' => 'integer',
    ];

    /**
     * Get the redemption records associated with this promo code.
     */
    public function redemptions()
    {
        return $this->hasMany(PromoCodeRedemption::class);
    }

    /**
     * Scope a query to only include active promo codes.
     */
    public function scopeIsActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * Scope a query to only include non-expired promo codes.
     */
    public function scopeIsNotExpired(Builder $query): void
    {
        $query->where(function ($q) {
            $q->whereNull('expires_at')
                ->orWhere('expires_at', '>', now());
        });
    }

    /**
     * Check if the promo code has reached its global usage limit.
     */
    public function hasReachedMaxUses(): bool
    {
        return $this->max_uses !== null && $this->use_count >= $this->max_uses;
    }
}

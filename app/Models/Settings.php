<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache; // For caching settings

class Settings extends Model
{
    use HasFactory;

    protected $fillable = [
        'optimizer_service_url',
        'optimizer_timeout',
        'unlock_price_amount',
        'unlock_currency',
        'unlock_currency_symbol',
        'unlock_currency_symbol_position',
        'notify_admin_on_payment',      // <-- ADDED
        'admin_notification_email',   // <-- ADDED
        'access_duration_days',
    ];

    protected $casts = [
        'unlock_price_amount' => 'decimal:2',
        'notify_admin_on_payment' => 'boolean', // <-- ADDED
        'access_duration_days' => 'integer',
    ];

    protected $table = 'settings';
    protected const CACHE_KEY = 'app_settings';

    public static function instance(bool $forceRefresh = false): self
    {
        return self::first();
    }

    /**
     * Clear the settings cache. Should be called after updating settings.
     */
    public static function clearCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    // --- Optional: Helper Methods ---

    /**
     * Format the unlock price for display.
     *
     * @return string
     */
    public function getFormattedUnlockPriceAttribute(): string
    {
        // Convert cents to dollars/base unit for display
        $amountFormatted = number_format($this->unlock_price_amount / 100, 2);

        if ($this->unlock_currentcy_symbol_position === 'before') {
            return $this->unlock_currentcy_symbol . $amountFormatted;
        } else {
            return $amountFormatted . $this->unlock_currentcy_symbol;
        }
    }
}

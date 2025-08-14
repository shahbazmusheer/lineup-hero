<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserTeamActivationSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'status', 'payment_id', 'promo_code_redemption_id',
        'slot_expires_at', 'team_id',
    ];

    protected $casts = [
        'slot_expires_at' => 'datetime',
    ];

    public function user() { return $this->belongsTo(User::class); }
    public function payment() { return $this->belongsTo(Payment::class); }
    public function promoCodeRedemption() { return $this->belongsTo(PromoCodeRedemption::class); }
    public function team() { return $this->belongsTo(Team::class); }
}
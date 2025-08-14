<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PromoCodeRedemption extends Model
{
    use HasFactory;
    public $timestamps = false;
    protected $fillable = [
        'user_id', 'promo_code_id',
        'redeemable_id', 'redeemable_type',
        'redeemed_at'
    ];
    protected $casts = [ 'redeemed_at' => 'datetime' ];

    public function redeemable() { return $this->morphTo(); }
    public function user() { return $this->belongsTo(User::class); }
    public function promoCode() { return $this->belongsTo(PromoCode::class); }
    public function organization() { return $this->belongsTo(Organization::class); } // New relationship
}
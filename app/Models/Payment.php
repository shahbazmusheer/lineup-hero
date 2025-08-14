<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Payment extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id', // User who made the payment
        'payable_id', 'payable_type',
        'organization_id', // Organization that was paid for/activated
        'stripe_payment_intent_id',
        // 'user_organization_access_code', // This code is on the Organization model now
        'amount', // Stored in cents
        'currency', 'status', 'paid_at',
    ];
    protected $casts = [ 'amount' => 'decimal:2', 'paid_at' => 'datetime' ];
    protected $appends = [];

    public function payable() { return $this->morphTo(); }

    public function user() { return $this->belongsTo(User::class); }
    public function organization() { return $this->belongsTo(Organization::class); } // New relationship

    protected function amount(): Attribute {
        return Attribute::make(get: fn ($value) => number_format($value / 100, 2));
    }
}
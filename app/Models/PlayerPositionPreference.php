<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class PlayerPositionPreference extends Pivot // Extend Pivot
{
    protected $table = 'player_position_preferences'; // Explicitly define table

    // Define relationships back if needed (optional for pivot)
    public function player()
    {
        return $this->belongsTo(Player::class);
    }

    public function position()
    {
        return $this->belongsTo(Position::class);
    }
}

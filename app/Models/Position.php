<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Position extends Model
{
    use HasFactory;
    protected $fillable = ['name', 'display_name', 'category', 'is_editable'];

    public function playerPreferences()
    {
        // Link to the pivot table entries
        return $this->hasMany(PlayerPositionPreference::class);
    }

    public function playersPreferred()
    {
        return $this->belongsToMany(Player::class, 'player_position_preferences')
                    ->wherePivot('preference_type', 'preferred');
    }

    public function playersRestricted()
    {
        return $this->belongsToMany(Player::class, 'player_position_preferences')
                    ->wherePivot('preference_type', 'restricted');
    }
}

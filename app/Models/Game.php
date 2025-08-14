<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Game extends Model
{
    use HasFactory;
    protected $fillable = [
        'team_id', 'opponent_name', 'game_date', 'innings',
        'location_type', 'lineup_data', 'submitted_at'
    ];

    protected $casts = [
        'game_date' => 'datetime',
        'submitted_at' => 'datetime',
        'lineup_data' => 'array', // Cast JSON text to array automatically
    ];

    /**
     * The accessors to append to the model's array form.
     * This will automatically add 'is_submitted' to all JSON responses.
     *
     * @var array
     */
    protected $appends = ['is_lineup_submitted'];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Accessor for the 'is_submitted' attribute.
     *
     * Determines if the game's lineup has been submitted based on the
     * presence of the 'submitted_at' timestamp.
     *
     * @return bool
     */
    public function getIsLineupSubmittedAttribute(): bool
    {
        return $this->submitted_at !== null;
    }
    // Later: Relationship to detailed lineup positions if you create separate tables
    // public function lineupPositions() { ... }
}

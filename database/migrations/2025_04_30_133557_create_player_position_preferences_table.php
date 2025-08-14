<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('player_position_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignId('position_id')->constrained('positions')->cascadeOnDelete();
            $table->enum('preference_type', ['preferred', 'restricted']); // 'preferred' or 'restricted'
            $table->timestamps();
            // $table->unique(['player_id', 'position_id', 'preference_type']);
            $table->unique(
                ['player_id', 'position_id', 'preference_type'],
                'player_pos_pref_type_unique' // <-- CUSTOM SHORT NAME HERE
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('player_position_preferences');
    }
};

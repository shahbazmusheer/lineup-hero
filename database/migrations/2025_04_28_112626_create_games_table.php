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
        Schema::create('games', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('opponent_name')->nullable();
            $table->dateTime('game_date')->nullable();
            $table->integer('innings')->default(6); // Default number of innings
            $table->enum('location_type', ['home', 'away']);
            $table->text('lineup_data')->nullable(); // Consider storing lineup as JSON? Or use separate lineup tables. Start simple.
            $table->timestamp('submitted_at')->nullable(); // Track when lineup was finalized/submitted
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('games');
    }
};

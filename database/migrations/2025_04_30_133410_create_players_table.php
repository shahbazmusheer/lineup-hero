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
        Schema::create('players', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained('teams')->cascadeOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('jersey_number')->nullable();
            $table->string('email')->nullable()->unique(); // Unique if provided
            $table->string('phone')->nullable()->unique(); // Unique if provided
            // Add other player stats placeholders if needed now
            // e.g., $table->float('calculated_innings_played_percentage')->nullable();
            // $table->foreignId('calculated_top_position_id')->nullable()->constrained('positions')->nullOnDelete();
            // $table->integer('calculated_avg_batting_location')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};

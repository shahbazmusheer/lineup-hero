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
        Schema::create('teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete(); // Link to the User who created/manages it
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete(); // Link to Organization
            $table->string('name');
            $table->string('season')->nullable(); // e.g., "Spring", "Summer", "Fall"
            $table->smallInteger('year')->nullable(); // e.g., 2024
            $table->enum('sport_type', ['baseball', 'softball']);
            $table->enum('team_type', ['travel', 'recreation', 'school']);
            $table->string('age_group'); // e.g., "11u", "Varsity"
            $table->string('city')->nullable();
            $table->string('state')->nullable();
            $table->string('country')->nullable();

            // For Path A: Direct Team Activation
            $table->string('direct_activation_status')->default('inactive');
            $table->timestamp('direct_activation_expires_at')->nullable();

            // For all teams: when their editability expires
            $table->timestamp('is_editable_until')->nullable();

            // For placeholder team workflow
            $table->boolean('is_setup_complete')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teams');
    }
};

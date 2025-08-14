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
        Schema::create('user_team_activation_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status')->default('available'); // available, used, expired
            $table->foreignId('payment_id')->nullable()->constrained('payments')->nullOnDelete();
            $table->foreignId('promo_code_redemption_id')->nullable()->constrained('promo_code_redemptions')->nullOnDelete();
            $table->timestamp('slot_expires_at'); // When the activation granted by this slot expires
            $table->foreignId('team_id')->nullable()->comment('Team eventually created using this slot')
                ->constrained('teams')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_team_activation_slots');
    }
};

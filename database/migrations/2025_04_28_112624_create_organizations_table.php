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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('organization_code')->unique();
            $table->string('email')->nullable()->unique(); // Orgs might not need direct login initially
            $table->string('password')->nullable();      // Make nullable if no direct login

            $table->enum('pricing_type', ['general', 'custom'])->default('general');
            $table->decimal('custom_price_amount', 8, 2)->nullable()->comment('Custom annual price in dollars');
            $table->foreignId('creator_user_id')->nullable()
                ->constrained('users')->onDelete('set null');
            // New fields based on latest feedback
            $table->unsignedInteger('annual_team_allocation')->default(0);
            $table->unsignedInteger('teams_created_this_period')->default(0);
            $table->string('subscription_status')->default('inactive');
            $table->timestamp('subscription_expires_at')->nullable();
            $table->string('stripe_customer_id')->nullable()->unique();
            $table->string('stripe_subscription_id')->nullable()->unique();
            $table->rememberToken(); // Keep for potential future login
            // Add other org details if needed (address, contact person, etc.)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('organizations');
    }
};

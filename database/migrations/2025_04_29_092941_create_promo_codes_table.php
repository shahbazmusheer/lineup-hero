<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promo_codes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // The code string itself (e.g., "FREEFALL24")
            $table->text('description')->nullable(); // Internal description for admins
            // $table->enum('discount_type', ['free_access', 'percentage', 'fixed_amount'])->default('free_access'); // Future flexibility
            // $table->decimal('discount_value', 8, 2)->nullable(); // For percentage/fixed amount
            $table->timestamp('expires_at')->nullable(); // When the code itself expires
            $table->unsignedInteger('max_uses')->nullable(); // Max times the code can be used globally (null = infinite)
            $table->unsignedInteger('use_count')->default(0); // How many times it has been used globally
            $table->unsignedInteger('max_uses_per_user')->default(1); // Max times one user can use this code (usually 1)
            // $table->unsignedInteger('duration_in_days')->nullable();
            $table->boolean('is_active')->default(true); // Admin can toggle activation
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promo_codes');
    }
};

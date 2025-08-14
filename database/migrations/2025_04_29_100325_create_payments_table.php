<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->cascadeOnDelete(); // User who paid
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete(); // Link to Organization

            // $table->dropForeign(['organization_id']); // If altering, drop old FK
            // $table->dropColumn('organization_id'); // If altering

            $table->morphs('payable'); // Adds payable_id (unsignedBigInteger) and payable_type (string)

            $table->string('stripe_payment_intent_id')->unique();
            $table->integer('amount'); // Cents
            $table->string('currency', 3);
            $table->string('status');
            $table->timestamp('paid_at');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};

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
        Schema::table('teams', function (Blueprint $table) {
            // Status options: 'inactive' (default), 'promo_active', 'paid_active'
            // Add more if needed (e.g., 'trial')
//            $table->string('access_status')->default('inactive')->after('state');
//            $table->timestamp('access_expires_at')->nullable()->after('access_status');
//            $table->index('access_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('teams', function (Blueprint $table) {
            $table->dropColumn(['access_status', 'access_expires_at']);
        });
    }
};

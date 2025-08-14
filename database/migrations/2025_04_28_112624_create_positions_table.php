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
        Schema::create('positions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique(); // e.g., P, C, 1B, OUT
            $table->string('display_name')->nullable();
            $table->string('category');     // e.g., INF, OF, PITCHER, CATCHER, SPECIAL
            $table->boolean('is_editable')->default(true); // Can users edit/delete this? (e.g., OUT should be false)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('positions');
    }
};

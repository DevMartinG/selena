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
        Schema::create('tender_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique(); // '1-CONVOCADO', 'D-DESIERTO', etc.
            $table->string('name'); // '1. CONVOCADO', 'DESIERTO', etc.
            $table->string('category')->default('normal'); // 'normal', 'special'
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_statuses');
    }
};

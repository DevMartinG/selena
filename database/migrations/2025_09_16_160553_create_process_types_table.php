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
        Schema::create('process_types', function (Blueprint $table) {
            $table->id();
            $table->string('code_short_type')->unique(); // AS, SIE, LP, CP, COMPRE
            $table->string('description_short_type'); // ADJUDICACION SIMPLIFICADA, etc.
            $table->string('year')->nullable(); // (2024), (2025)
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('process_types');
    }
};

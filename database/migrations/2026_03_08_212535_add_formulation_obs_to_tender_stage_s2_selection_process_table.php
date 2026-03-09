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
        Schema::table('tender_stage_s2_selection_process', function (Blueprint $table) {
            $table->date('formulation_obs')
                ->nullable()
                ->after('participants_registration')
                ->comment('Fecha de formulación de consultas y observaciones (Electrónica)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tender_stage_s2_selection_process', function (Blueprint $table) {
            $table->dropColumn('formulation_obs');
        });
    }
};

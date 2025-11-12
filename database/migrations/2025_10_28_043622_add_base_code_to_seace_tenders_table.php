<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agrega el campo base_code para agrupar registros por proceso base
     */
    public function up(): void
    {
        Schema::table('seace_tenders', function (Blueprint $table) {
            $table->string('base_code')->nullable()->after('code_full')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('seace_tenders', function (Blueprint $table) {
            $table->dropColumn('base_code');
        });
    }
};

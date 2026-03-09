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
        Schema::table('tender_deadline_rules', function (Blueprint $table) {
            // Nueva columna que apunta a process_types
            $table->foreignId('process_type_id')->nullable()
                  ->constrained('process_types')
                  ->onDelete('set null')
                  ->after('created_by'); // opcional, para ubicarla después de created_by
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tender_deadline_rules', function (Blueprint $table) {
            $table->dropForeign(['process_type_id']);
            $table->dropColumn('process_type_id');
        });
    }
};

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
        Schema::table('tenders', function (Blueprint $table) {
            // Campos de auditoría de usuario
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que creó el procedimiento');
            $table->foreignId('updated_by')->nullable()->constrained('users')->onDelete('set null')->comment('Usuario que modificó por última vez');
            
            // Índices para optimizar consultas
            $table->index('created_by');
            $table->index('updated_by');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            // Eliminar índices primero
            $table->dropIndex(['created_by']);
            $table->dropIndex(['updated_by']);
            
            // Eliminar foreign keys y columnas
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['created_by', 'updated_by']);
        });
    }
};

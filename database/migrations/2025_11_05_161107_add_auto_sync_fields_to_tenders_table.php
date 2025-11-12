<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega campos para controlar la sincronización automática desde SeaceTenderCurrent.
     * Estos campos permiten que el sistema sincronice automáticamente campos comunes
     * cuando se importan nuevos SeaceTender, pero respeta cambios manuales del usuario.
     */
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            // Flag para habilitar/deshabilitar sincronización automática
            $table->boolean('auto_sync_from_seace')
                ->default(true)
                ->after('seace_tender_current_id')
                ->comment('Si true, sincroniza automáticamente campos desde SeaceTenderCurrent cuando cambia el lookup');
            
            // Timestamp de última actualización manual del usuario
            // Si este campo es más reciente que SeaceTenderCurrent.updated_at,
            // no se sincroniza automáticamente para respetar cambios manuales
            $table->timestamp('last_manual_update_at')
                ->nullable()
                ->after('auto_sync_from_seace')
                ->comment('Última vez que el usuario modificó manualmente campos sincronizables');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn(['auto_sync_from_seace', 'last_manual_update_at']);
        });
    }
};

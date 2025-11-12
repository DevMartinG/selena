<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Crea tabla lookup simple que mapea base_code → latest_seace_tender_id
     * Esta tabla actúa como "cache" o "vista materializada" que siempre contiene
     * el SeaceTender más reciente por base_code.
     */
    public function up(): void
    {
        Schema::create('seace_tender_current', function (Blueprint $table) {
            // base_code como PRIMARY KEY (único por proceso)
            $table->string('base_code')->primary();
            
            // FK al SeaceTender más reciente
            $table->foreignId('latest_seace_tender_id')
                ->constrained('seace_tenders')
                ->onDelete('restrict'); // Prevenir eliminación si está en uso
            
            // Timestamp de última actualización
            $table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
            
            // Índice para búsquedas rápidas por seace_tender_id
            $table->index('latest_seace_tender_id');
        });
        
        // ========================================
        // POBLAR CON DATOS EXISTENTES
        // ========================================
        // Obtener el SeaceTender más reciente por cada base_code
        // Ordenar por: code_attempt DESC, publish_date DESC, created_at DESC
        $baseCodes = DB::table('seace_tenders')
            ->whereNotNull('base_code')
            ->distinct()
            ->pluck('base_code');
        
        foreach ($baseCodes as $baseCode) {
            // Obtener el más reciente por base_code
            $latest = DB::table('seace_tenders')
                ->where('base_code', $baseCode)
                ->orderBy('code_attempt', 'desc')
                ->orderBy('publish_date', 'desc')
                ->orderBy('created_at', 'desc')
                ->first();
            
            if ($latest) {
                DB::table('seace_tender_current')->insert([
                    'base_code' => $baseCode,
                    'latest_seace_tender_id' => $latest->id,
                    'updated_at' => now(),
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seace_tender_current');
    }
};

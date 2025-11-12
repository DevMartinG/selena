<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\Tender;
use App\Models\SeaceTenderCurrent;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Agrega seace_tender_current_id a tenders y migra datos existentes.
     * Si un tender tiene seace_tender_id, se busca su base_code y se asigna
     * el seace_tender_current_id correspondiente.
     */
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            // Agregar campo nullable inicialmente
            $table->string('seace_tender_current_id')
                ->nullable()
                ->after('seace_tender_id')
                ->comment('FK a seace_tender_current.base_code (lookup del más reciente)');
        });
        
        // ========================================
        // MIGRAR DATOS EXISTENTES
        // ========================================
        // Para cada tender que tiene seace_tender_id, buscar su base_code
        // y asignar el seace_tender_current_id correspondiente
        $tenders = Tender::whereNotNull('seace_tender_id')
            ->with('seaceTender')
            ->get();
        
        foreach ($tenders as $tender) {
            $seaceTender = $tender->seaceTender;
            
            if ($seaceTender && $seaceTender->base_code) {
                // Buscar el registro en seace_tender_current
                $current = SeaceTenderCurrent::find($seaceTender->base_code);
                
                if ($current) {
                    // Actualizar tender con el base_code del lookup
                    $tender->update([
                        'seace_tender_current_id' => $seaceTender->base_code,
                    ]);
                }
            }
        }
        
        // Agregar Foreign Key constraint después de migrar datos
        Schema::table('tenders', function (Blueprint $table) {
            $table->foreign('seace_tender_current_id')
                ->references('base_code')
                ->on('seace_tender_current')
                ->onDelete('set null'); // Si se elimina el lookup, poner NULL
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropForeign(['seace_tender_current_id']);
            $table->dropColumn('seace_tender_current_id');
        });
    }
};

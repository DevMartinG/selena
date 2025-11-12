<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use App\Models\ProcessType;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * Esta migración convierte process_type de string a Foreign Key:
     * 1. Agrega process_type_id (nullable temporalmente)
     * 2. Migra datos existentes de process_type (string) a process_type_id (FK)
     * 3. Crea registro "Sin Clasificar" en process_types si no existe
     * 4. Hace process_type_id NOT NULL
     * 5. Elimina la columna process_type (string)
     * 6. Agrega Foreign Key constraint
     */
    public function up(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            // Paso 1: Agregar process_type_id como nullable temporalmente
            $table->foreignId('process_type_id')->nullable()->after('entity_name');
        });

        // Paso 2: Migrar datos existentes
        // Primero, asegurarse de que existe "Sin Clasificar" en process_types
        $sinClasificar = ProcessType::where('description_short_type', 'Sin Clasificar')->first();
        
        if (!$sinClasificar) {
            // Crear registro "Sin Clasificar" si no existe
            $sinClasificar = ProcessType::create([
                'code_short_type' => 'SC',
                'description_short_type' => 'Sin Clasificar',
                'year' => date('Y'),
            ]);
        }

        // Migrar cada tender: buscar ProcessType por description_short_type
        // Usar whereNotNull para obtener todos los tenders que tienen process_type (string)
        $tenders = DB::table('tenders')
            ->whereNotNull('process_type')
            ->get();
        
        foreach ($tenders as $tender) {
            $processTypeString = $tender->process_type;
            
            // Buscar ProcessType por description_short_type
            $processType = ProcessType::where('description_short_type', $processTypeString)->first();
            
            // Si no se encuentra, usar "Sin Clasificar"
            $processTypeId = $processType ? $processType->id : $sinClasificar->id;
            
            // Actualizar el tender con el process_type_id
            DB::table('tenders')
                ->where('id', $tender->id)
                ->update(['process_type_id' => $processTypeId]);
        }

        // Asegurar que todos los tenders tengan process_type_id (si alguno quedó NULL, usar "Sin Clasificar")
        DB::table('tenders')
            ->whereNull('process_type_id')
            ->update(['process_type_id' => $sinClasificar->id]);

        // Paso 3: Hacer process_type_id NOT NULL después de migrar
        Schema::table('tenders', function (Blueprint $table) {
            $table->foreignId('process_type_id')->nullable(false)->change();
        });

        // Paso 4: Eliminar la columna process_type (string)
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn('process_type');
        });

        // Paso 5: Agregar Foreign Key constraint
        Schema::table('tenders', function (Blueprint $table) {
            $table->foreign('process_type_id')
                ->references('id')
                ->on('process_types')
                ->onDelete('restrict'); // Restrict para evitar eliminar process_types que están en uso
        });
    }

    /**
     * Reverse the migrations.
     * 
     * Para revertir:
     * 1. Eliminar FK constraint
     * 2. Agregar process_type (string) de vuelta
     * 3. Migrar datos de process_type_id a process_type (string)
     * 4. Eliminar process_type_id
     */
    public function down(): void
    {
        Schema::table('tenders', function (Blueprint $table) {
            // Paso 1: Eliminar Foreign Key constraint
            $table->dropForeign(['process_type_id']);
        });

        // Paso 2: Agregar process_type (string) de vuelta
        Schema::table('tenders', function (Blueprint $table) {
            $table->string('process_type')->after('entity_name');
        });

        // Paso 3: Migrar datos de process_type_id a process_type (string)
        $tenders = DB::table('tenders')
            ->join('process_types', 'tenders.process_type_id', '=', 'process_types.id')
            ->select('tenders.id', 'process_types.description_short_type')
            ->get();

        foreach ($tenders as $tender) {
            DB::table('tenders')
                ->where('id', $tender->id)
                ->update(['process_type' => $tender->description_short_type]);
        }

        // Paso 4: Eliminar process_type_id
        Schema::table('tenders', function (Blueprint $table) {
            $table->dropColumn('process_type_id');
        });
    }
};

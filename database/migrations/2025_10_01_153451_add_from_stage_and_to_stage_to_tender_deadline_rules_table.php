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
            // Agregar nuevos campos para etapas origen y destino
            $table->string('from_stage', 10)->after('id')->comment('Etapa origen: S1, S2, S3, S4');
            $table->string('to_stage', 10)->after('from_stage')->comment('Etapa destino: S1, S2, S3, S4');

            // Migrar datos existentes: usar stage_type para ambos campos
            // Esto se hará en el método down() para mantener compatibilidad
        });

        // Migrar datos existentes
        \DB::statement('UPDATE tender_deadline_rules SET from_stage = stage_type, to_stage = stage_type WHERE from_stage IS NULL');

        // Hacer los campos requeridos después de migrar los datos
        Schema::table('tender_deadline_rules', function (Blueprint $table) {
            $table->string('from_stage', 10)->nullable(false)->change();
            $table->string('to_stage', 10)->nullable(false)->change();

            // Agregar índices para los nuevos campos
            $table->index(['from_stage', 'is_active']);
            $table->index(['to_stage', 'is_active']);
            $table->index(['from_stage', 'to_stage']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tender_deadline_rules', function (Blueprint $table) {
            // Eliminar índices
            $table->dropIndex(['from_stage', 'is_active']);
            $table->dropIndex(['to_stage', 'is_active']);
            $table->dropIndex(['from_stage', 'to_stage']);

            // Eliminar campos
            $table->dropColumn(['from_stage', 'to_stage']);
        });
    }
};

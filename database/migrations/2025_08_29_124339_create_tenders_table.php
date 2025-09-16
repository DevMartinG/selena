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
        Schema::create('tenders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code_sequence'); // e.g. 30
            $table->string('code_type'); // e.g. LP Homologación-ABR
            $table->string('code_short_type'); // e.g. LP Homologación-ABR
            $table->string('code_year'); // e.g. 2025
            $table->unsignedTinyInteger('code_attempt')->default(1); // e.g. 1 (intento de licitación)
            $table->string('code_full')->unique(); // e.g. 30-LPHomologación-ABR (sin espacios)
            // $table->unsignedInteger('sequence_number'); // Nº correlativo

            // General Info
            $table->string('entity_name'); // Nombre de la entidad
            $table->string('process_type'); // Tipo de proceso
            $table->string('identifier')->unique(); // Nomenclatura
            $table->string('contract_object'); // Objeto del contratación
            $table->text('object_description'); // Descripción del objeto
            $table->decimal('estimated_referenced_value', 15, 2); // Valor Referencial / Valor Estimado
            $table->string('currency_name'); // Moneda
            $table->string('current_status'); // Estado Actual

            // Los campos de etapas (S1, S2, S3, S4) se han movido a tablas separadas
            // para mejor organización y escalabilidad

            // Datos Adicionales
            $table->text('observation')->nullable(); // Observaciones
            $table->text('selection_comittee')->nullable(); // OEC/ Comité de Selección
            // $table->text('contract_execution')->nullable(); // Ejecución Contractual

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tenders');
    }
};

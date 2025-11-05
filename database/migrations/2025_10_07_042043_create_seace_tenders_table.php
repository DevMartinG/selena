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
        Schema::create('seace_tenders', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('code_sequence'); // e.g. 30
            $table->string('code_type'); // e.g. LP Homologación-ABR
            $table->string('code_short_type'); // e.g. LP Homologación-ABR
            $table->string('code_year'); // e.g. 2025
            $table->unsignedTinyInteger('code_attempt')->default(1); // e.g. 1 (intento de licitación)
            $table->string('code_full')->index(); // e.g. 30-LPHomologación-ABR (sin espacios) - Indexado para búsquedas rápidas
            // $table->unsignedInteger('sequence_number'); // Nº correlativo

            // General Info
            $table->string('entity_name')->default('GOBIERNO REGIONAL DE PUNO SEDE CENTRAL'); // Nombre de la entidad
            $table->string('process_type'); // Tipo de proceso
            $table->string('identifier'); // Nomenclatura - Sin unique, permite múltiples registros
            $table->string('contract_object'); // Objeto del contratación
            $table->text('object_description'); // Descripción del objeto
            $table->decimal('estimated_referenced_value', 15, 2); // Valor Referencial / Valor Estimado
            $table->string('currency_name'); // Moneda
            $table->foreignId('tender_status_id')->nullable()->constrained('tender_statuses')->onDelete('set null'); // Estado Actual

            // Los campos de etapas (S1, S2, S3, S4) se han movido a tablas separadas
            // para mejor organización y escalabilidad

            // Datos Adicionales - MODIFICADOS para SeaceTender
            $table->date('publish_date'); // Fecha de publicación en SEACE - Siempre tiene valor
            $table->time('publish_date_time'); // Hora de publicación en SEACE - Siempre tiene valor
            $table->string('resumed_from')->nullable(); // Procedimiento del cual se reanuda
            // $table->text('contract_execution')->nullable(); // Ejecución Contractual

            // ✅ UNICIDAD COMPUESTA: Un registro es único por la combinación de estos 3 campos
            // identifier + publish_date + publish_date_time
            // Esto permite múltiples registros del mismo proceso con diferentes fechas/horas
            $table->unique(['identifier', 'publish_date', 'publish_date_time'], 'seace_tenders_unique_composite');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('seace_tenders');
    }
};
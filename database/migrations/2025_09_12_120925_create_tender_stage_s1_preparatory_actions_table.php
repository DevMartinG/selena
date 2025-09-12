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
        Schema::create('tender_stage_s1_preparatory_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_stage_id')->constrained('tender_stages')->onDelete('cascade');
            
            // PRESENTACION DE REQUERIMIENTO DE BIEN
            $table->string('request_presentation_doc')->nullable()->comment('Documento de presentación de requerimiento');
            $table->date('request_presentation_date')->nullable()->comment('Fecha de presentación de requerimiento');
            
            // INDAGACION DE MERCADO
            $table->string('market_indagation_doc')->nullable()->comment('Expediente de indagación de mercado');
            $table->date('market_indagation_date')->nullable()->comment('Fecha de indagación de mercado');
            
            // CERTIFICACION
            $table->boolean('with_certification')->default(true)->comment('Tiene certificación');
            $table->date('certification_date')->nullable()->comment('Fecha de certificación');
            $table->string('no_certification_reason')->nullable()->comment('Motivo de no certificación');
            
            // APROBACIONES Y DESIGNACIONES
            $table->date('approval_expedient_date')->nullable()->comment('Fecha de aprobación del expediente');
            $table->date('selection_committee_date')->nullable()->comment('Fecha de designación del comité');
            $table->date('administrative_bases_date')->nullable()->comment('Fecha de elaboración de bases administrativas');
            $table->date('approval_expedient_format_2')->nullable()->comment('Fecha de aprobación formato 2');
            
            $table->timestamps();
            
            // Índice único para evitar duplicados
            $table->unique('tender_stage_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_stage_s1_preparatory_actions');
    }
};

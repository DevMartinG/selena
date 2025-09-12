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
        Schema::create('tender_stage_s2_selection_process', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_stage_id')->constrained('tender_stages')->onDelete('cascade');
            
            // PUBLICACION Y REGISTRO
            $table->date('published_at')->comment('Fecha de publicación en SEACE');
            $table->date('participants_registration')->nullable()->comment('Registro de participantes');
            $table->string('restarted_from')->nullable()->comment('Reiniciado desde');
            $table->string('cui_code')->nullable()->comment('Código CUI');
            
            // PROCESO DE SELECCION
            $table->date('absolution_obs')->nullable()->comment('Absolución de consultas/observaciones');
            $table->date('base_integration')->nullable()->comment('Integración de bases');
            $table->date('offer_presentation')->nullable()->comment('Presentación de ofertas');
            $table->date('offer_evaluation')->nullable()->comment('Evaluación de propuestas');
            
            // ADJUDICACION
            $table->date('award_granted_at')->nullable()->comment('Otorgamiento de buena pro');
            $table->date('award_consent')->nullable()->comment('Consentimiento de buena pro');
            $table->date('appeal_date')->nullable()->comment('Fecha de apelación');
            
            // DATOS DEL ADJUDICADO
            $table->string('awarded_tax_id')->nullable()->comment('RUC del adjudicado');
            $table->text('awarded_legal_name')->nullable()->comment('Razón social del adjudicado');
            
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
        Schema::dropIfExists('tender_stage_s2_selection_process');
    }
};

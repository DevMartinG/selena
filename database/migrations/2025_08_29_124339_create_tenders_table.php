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
            $table->string('code')->unique(); // para manejar correlativo por tipo de nomenclatura
            $table->unsignedInteger('sequence_number');// Nº correlativo
            $table->string('entity_name');// Nombre de la entidad
            $table->date('publishet_date');// Fecha de publicación
            $table->string('identifier')->unique();// Nomenclatura
            $table->date('restarted_from')->nullable();// Fecha de reinicio
            $table->string('contract_object');// Objeto del contratación
            $table->text('object_description');// Descripción del objeto
            $table->string('cui_code')->nullable();// Código CUI
            $table->decimal('estimated_referenced_value',15,2);// Valor Referencial / Valor Estimado
            $table->string('currency_name'); // Moneda
            $table->date('absolution_obs')->nullable(); // Absolucion de Consultas / Obs Integracion de Bases
            $table->date('offer_presentation')->nullable(); // Presentación de Ofertas
            $table->date('award_granted_at')->nullable(); // Otorgamiento de la Buena Pro
            $table->date('award_consent')->nullable(); // Consentimiento de la Buena Pro
            $table->string('current_status'); // Estado Actual
            $table->string('awarded_tax_id')->nullable(); // RUC del Adjudicado
            $table->text('awarded_legal_name')->nullable(); // Razón Social del Postor Adjudicado
            $table->decimal('awarded_amount',15,2)->nullable(); // Monto Adjudicado
            $table->date('contract_signing')->nullable(); // Fecha de Suscripción del Contrato
            $table->decimal('adjusted_amount',15,2)->nullable(); // Monto diferencial (VE/VF vs Oferta Economica)
            $table->text('observation')->nullable(); // Observaciones
            $table->text('selection_comittee')->nullable(); // OEC/ Comité de Selección
            $table->text('contract_execution')->nullable(); // Ejecución Contractual
            $table->text('contract_details')->nullable(); // Datos del contrato
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

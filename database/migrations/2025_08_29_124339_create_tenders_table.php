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

            // S1 : Actuaciones Preparatorias
            // PRESENTACION DE REQUERIMIENTO DE BIEN
            $table->string('s1_request_presentation_doc')->nullable(); // PRESENTACION DE REQUERIMIENTO DE BIEN 
            $table->date('s1_request_presentation_date')->nullable(); // FECHA DE PRESENTACION DE REQUERIMIENTO DE BIEN
            $table->string('s1_market_indagation_doc')->nullable(); // Expediente de INDAGACION DE MERCADO
            $table->date('s1_market_indagation_date')->nullable(); // FECHA DE INDAGACION DE MERCADO
            $table->boolean('s1_with_certification')->default(true); // Tiene Certificación?
            $table->date('s1_certification_date')->nullable(); // FECHA DE CERTIFICACION
            $table->string('s1_no_certification_reason')->nullable(); // MOTIVO DE NO CERTIFICACION
            $table->date('s1_approval_expedient_date')->nullable(); // FECHA DE APROBACION DEL EXPEDIENTE DE CONTRATACION
            $table->date('s1_selection_committee_date')->nullable(); // FECHA DE DESIGNACION DEL COMITÉ DE SELECCIÓN
            $table->date('s1_administrative_bases_date')->nullable(); // FECHA DE ELABORACION DE LAS BASES ADMINISTRATIVAS
            $table->date('s1_approval_expedient_format_2')->nullable(); // FECHA DE APROBACION DEL EXPEDIENTE DE CONTRATACION FORMATO 2
            
            
            // S2 : Procedimiento de Selección
            $table->date('s2_published_at'); // Fecha de publicación - REGISTRO DE CONVOCATORIA EN EL SEACE
            $table->date('s2_participants_registration')->nullable(); // REGISTRO DE PARTICIPANTES 
            $table->string('s2_restarted_from')->nullable(); //  Reiniciado desde
            $table->string('s2_cui_code')->nullable(); // Código CUI
            $table->date('s2_absolution_obs')->nullable(); // Absolucion de Consultas / Obs Integracion de Bases
            $table->date('s2_base_integration')->nullable(); // Integracion de Bases
            $table->date('s2_offer_presentation')->nullable(); // Presentación de Ofertas
            $table->date('s2_offer_evaluation')->nullable(); // CALIFICACION Y EVALUACION DE PROPUESTAS
            $table->date('s2_award_granted_at')->nullable(); // Otorgamiento de la Buena Pro
            $table->date('s2_award_consent')->nullable(); // Consentimiento de la Buena Pro
            $table->date('s2_appeal_date')->nullable(); // Fecha de Apealación
            $table->string('s2_awarded_tax_id')->nullable(); // RUC del Adjudicado
            $table->text('s2_awarded_legal_name')->nullable(); // Razón Social del Postor Adjudicado
            
            // S3 : Suscripción del Contrato
            $table->date('s3_doc_sign_presentation_date')->nullable(); // PRESENTAMIENTO DE LOS DOCUMENTOS PARA FIRMA E CONTRATO 
            $table->date('s3_contract_signing')->nullable(); // Fecha de Suscripción del Contrato - SUSCRIPCION DE CONTRATO
            $table->decimal('s3_awarded_amount', 15, 2)->nullable(); // Monto Adjudicado
            $table->decimal('s3_adjusted_amount', 15, 2)->nullable(); // Monto diferencial (VE/VF vs Oferta Economica)
            
            
            // S4 : TIEMPO DE EJECUCION
            $table->text('s4_contract_details')->nullable(); // Datos del contrato - TIPO DE DOCUMENTO
            $table->text('s4_contract_signing')->nullable(); // SUSCRIPCION DE CONTRATO
            $table->text('s4_contract_vigency_date')->nullable(); // FECHA DE VIGENCIA DE CONTRATO

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

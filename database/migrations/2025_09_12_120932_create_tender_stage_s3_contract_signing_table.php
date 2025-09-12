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
        Schema::create('tender_stage_s3_contract_signing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_stage_id')->constrained('tender_stages')->onDelete('cascade');
            
            // SUSCRIPCION DEL CONTRATO
            $table->date('doc_sign_presentation_date')->nullable()->comment('Presentación de documentos para firma');
            $table->date('contract_signing')->nullable()->comment('Fecha de suscripción del contrato');
            
            // MONTOS
            $table->decimal('awarded_amount', 15, 2)->nullable()->comment('Monto adjudicado');
            $table->decimal('adjusted_amount', 15, 2)->nullable()->comment('Monto diferencial (VE/VF vs Oferta Económica)');
            
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
        Schema::dropIfExists('tender_stage_s3_contract_signing');
    }
};

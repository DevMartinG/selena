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
        Schema::create('tender_stage_s4_execution_time', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tender_stage_id')->constrained('tender_stages')->onDelete('cascade');
            
            // TIEMPO DE EJECUCION
            $table->text('contract_details')->nullable()->comment('Datos del contrato - Tipo de documento');
            $table->text('contract_signing')->nullable()->comment('Suscripción de contrato');
            $table->text('contract_vigency_date')->nullable()->comment('Fecha de vigencia de contrato');
            
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
        Schema::dropIfExists('tender_stage_s4_execution_time');
    }
};

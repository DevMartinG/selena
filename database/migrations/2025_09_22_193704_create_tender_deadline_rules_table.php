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
        Schema::create('tender_deadline_rules', function (Blueprint $table) {
            $table->id();
            
            // Información de la regla
            $table->string('stage_type', 10)->comment('Etapa: S1, S2, S3, S4');
            $table->string('from_field', 100)->comment('Campo de fecha origen');
            $table->string('to_field', 100)->comment('Campo de fecha destino');
            $table->integer('legal_days')->comment('Días hábiles permitidos');
            
            // Configuración de la regla
            $table->boolean('is_active')->default(true)->comment('Si la regla está activa');
            $table->boolean('is_mandatory')->default(true)->comment('Si es obligatorio cumplir');
            $table->text('description')->nullable()->comment('Descripción de la regla');
            
            // Auditoría
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null')->comment('SuperAdmin que creó la regla');
            
            $table->timestamps();
            
            // Índices para optimización
            $table->index(['stage_type', 'is_active']);
            $table->index(['from_field', 'to_field']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_deadline_rules');
    }
};

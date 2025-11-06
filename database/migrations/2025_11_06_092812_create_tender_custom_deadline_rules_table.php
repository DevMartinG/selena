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
        Schema::create('tender_custom_deadline_rules', function (Blueprint $table) {
            $table->id();
            
            // Relación con Tender
            $table->foreignId('tender_id')->constrained('tenders')->onDelete('cascade');
            
            // Identificación del campo
            $table->string('stage_type', 10)->comment('S1, S2, S3, S4');
            $table->string('field_name', 100)->comment('Ej: s1Stage.approval_expedient_date');
            
            // Campo origen (referencia a campo de fecha origen)
            $table->string('from_stage', 10)->comment('Etapa origen');
            $table->string('from_field', 100)->comment('Campo origen');
            
            // Fecha personalizada
            $table->date('custom_date')->comment('Fecha personalizada definida por el usuario');
            
            // Evidencias
            $table->string('evidence_image')->comment('Ruta de la imagen captura (obligatoria)');
            $table->string('evidence_pdf')->nullable()->comment('Ruta del PDF completo (opcional)');
            
            // Metadatos
            $table->text('description')->nullable()->comment('Descripción opcional');
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            
            $table->timestamps();
            
            // Índices
            $table->unique(['tender_id', 'stage_type', 'field_name'], 'unique_tender_field_rule');
            $table->index(['tender_id', 'stage_type']);
            $table->index(['stage_type', 'field_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tender_custom_deadline_rules');
    }
};

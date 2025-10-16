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
        Schema::create('manual_usuario', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_archivo')->comment('Nombre del archivo PDF del manual');
            $table->string('ruta_archivo')->comment('Ruta donde se almacena el archivo');
            $table->string('version')->default('1.0')->comment('Versión del manual');
            $table->text('link_videos')->nullable()->comment('Link a los videos tutoriales');
            $table->foreignId('subido_por')->constrained('users')->onDelete('cascade')->comment('Usuario que subió el manual');
            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->index('version');
            $table->index('subido_por');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('manual_usuario');
    }
};
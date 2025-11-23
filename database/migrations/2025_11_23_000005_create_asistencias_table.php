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
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('parada_ruta_id')->constrained('paradas_ruta')->onDelete('cascade');
            $table->foreignId('hijo_id')->constrained('hijos')->onDelete('cascade');
            $table->foreignId('viaje_id')->constrained('viajes')->onDelete('cascade');
            
            // Información del escaneo QR
            $table->string('codigo_qr_escaneado');
            $table->timestamp('hora_escaneo');
            
            // Estado de asistencia
            $table->enum('estado', ['presente', 'ausente', 'justificado'])->default('presente');
            
            // Validación de ubicación (GPS del chofer al escanear)
            $table->decimal('latitud_escaneo', 10, 8)->nullable();
            $table->decimal('longitud_escaneo', 11, 8)->nullable();
            
            // Observaciones
            $table->text('observaciones')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('viaje_id');
            $table->index('hijo_id');
            $table->index('parada_ruta_id');
            $table->index('hora_escaneo');
            
            // Constraint: No permitir duplicados de asistencia por hijo en el mismo viaje
            $table->unique(['viaje_id', 'hijo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};

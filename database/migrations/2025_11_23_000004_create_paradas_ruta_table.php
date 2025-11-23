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
        Schema::create('paradas_ruta', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('ruta_id')->constrained('rutas')->onDelete('cascade');
            $table->foreignId('confirmacion_id')->constrained('confirmaciones_viaje')->onDelete('cascade');
            
            // Orden en la ruta
            $table->integer('orden'); // 1, 2, 3, 4...
            
            // Información de ubicación
            $table->text('direccion');
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            
            // Tiempos y distancias
            $table->time('hora_estimada');
            $table->decimal('distancia_desde_anterior_km', 8, 2)->default(0);
            $table->integer('tiempo_desde_anterior_min')->default(0);
            
            // Información del clustering
            $table->integer('cluster_asignado')->nullable();
            
            // Estado de la parada
            $table->enum('estado', ['pendiente', 'en_camino', 'completada', 'omitida'])->default('pendiente');
            
            $table->timestamps();
            
            // Índices
            $table->index('ruta_id');
            $table->index(['ruta_id', 'orden']); // Para ordenar paradas
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('paradas_ruta');
    }
};

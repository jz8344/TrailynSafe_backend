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
        Schema::create('rutas', function (Blueprint $table) {
            $table->id();
            
            // Información básica
            $table->string('nombre'); // Auto-generado: "Ruta Viaje #123 - Escuela X"
            $table->text('descripcion')->nullable();
            
            // Relaciones (1:1 con viajes)
            $table->unsignedBigInteger('viaje_id'); // Foreign key se agregará después
            $table->foreignId('escuela_id')->constrained('escuelas')->onDelete('restrict');
            
            // Datos calculados por k-Means
            $table->decimal('distancia_total_km', 8, 2)->default(0);
            $table->integer('tiempo_estimado_minutos')->default(0);
            
            // Estado de la ruta
            $table->enum('estado', ['pendiente', 'activa', 'en_progreso', 'completada', 'cancelada'])->default('activa');
            
            // Información del algoritmo
            $table->string('algoritmo_utilizado')->default('k-means-clustering');
            $table->json('parametros_algoritmo')->nullable(); // Detalles del clustering
            $table->timestamp('fecha_generacion')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index('viaje_id');
            $table->index('estado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rutas');
    }
};

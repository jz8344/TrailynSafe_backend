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
            $table->foreignId('viaje_id')->constrained('viajes')->onDelete('cascade');
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
            $table->unique('viaje_id'); // Relación 1:1
            $table->index('estado');
        });
        
        // Agregar foreign key a viajes después de crear rutas
        Schema::table('viajes', function (Blueprint $table) {
            // Ya está creado en la migración anterior, pero sin constraint
            // porque rutas no existía. Ahora agregamos el constraint completo.
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

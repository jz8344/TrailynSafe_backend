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
        Schema::create('confirmaciones_viaje', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('viaje_id')->constrained('viajes')->onDelete('cascade');
            $table->foreignId('hijo_id')->constrained('hijos')->onDelete('cascade');
            $table->foreignId('padre_id')->constrained('usuarios')->onDelete('cascade');
            
            // Información de ubicación del padre
            $table->text('direccion_recogida');
            $table->text('referencia')->nullable(); // "Casa azul con reja blanca"
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            
            // Metadatos de confirmación
            $table->timestamp('hora_confirmacion');
            $table->enum('estado', ['confirmado', 'cancelado', 'no_asistira'])->default('confirmado');
            
            // Datos asignados después de k-Means
            $table->integer('orden_recogida')->nullable(); // Orden en la ruta
            $table->time('hora_estimada_recogida')->nullable(); // Calculada por k-Means
            
            $table->timestamps();
            
            // Índices
            $table->index('viaje_id');
            $table->index('hijo_id');
            $table->index('padre_id');
            $table->index('estado');
            
            // Constraint: Un hijo solo puede tener una confirmación activa por viaje
            $table->unique(['viaje_id', 'hijo_id', 'estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('confirmaciones_viaje');
    }
};

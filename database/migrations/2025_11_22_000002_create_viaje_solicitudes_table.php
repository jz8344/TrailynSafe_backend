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
        Schema::create('viaje_solicitudes', function (Blueprint $table) {
            $table->id();
            
            // Relaciones
            $table->foreignId('viaje_id')->constrained('viajes')->onDelete('cascade');
            $table->foreignId('hijo_id')->constrained('hijos')->onDelete('cascade');
            $table->foreignId('padre_id')->constrained('usuarios')->onDelete('cascade');
            
            // Estado de la solicitud
            $table->enum('estado_confirmacion', ['pendiente', 'aceptado', 'rechazado', 'cancelado'])->default('pendiente');
            
            // Geolocalización del punto de recogida
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->string('direccion_formateada', 500)->nullable();
            
            // Fechas de control
            $table->timestamp('fecha_confirmacion')->nullable();
            $table->timestamp('fecha_rechazo')->nullable();
            
            // Información adicional
            $table->text('notas_padre')->nullable();
            $table->text('notas_admin')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->index(['viaje_id', 'estado_confirmacion']);
            $table->index(['padre_id', 'estado_confirmacion']);
            $table->index('hijo_id');
            
            // Evitar solicitudes duplicadas
            $table->unique(['viaje_id', 'hijo_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viaje_solicitudes');
    }
};

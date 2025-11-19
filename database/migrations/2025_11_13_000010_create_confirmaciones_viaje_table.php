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
            $table->foreignId('viaje_id')->constrained('viajes')->onDelete('cascade');
            $table->foreignId('hijo_id')->constrained('hijos')->onDelete('cascade');
            $table->foreignId('usuario_id')->constrained('usuarios')->onDelete('cascade');
            
            // Coordenadas de recogida específicas para este viaje
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            $table->string('direccion_recogida', 500);
            
            // Estado de la confirmación
            // confirmado, cancelado, completado (niño recogido), ausente
            $table->enum('estado', [
                'confirmado',
                'cancelado',
                'completado',
                'ausente'
            ])->default('confirmado');
            
            // QR escaneado por el chofer
            $table->boolean('qr_escaneado')->default(false);
            $table->timestamp('hora_escaneo_qr')->nullable();
            
            // Orden de recogida en la ruta
            $table->integer('orden_recogida')->nullable();
            
            // Hora estimada de recogida
            $table->time('hora_estimada_recogida')->nullable();
            
            $table->timestamps();
            
            // Índices
            $table->unique(['viaje_id', 'hijo_id']);
            $table->index('estado');
            $table->index(['viaje_id', 'estado']);
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

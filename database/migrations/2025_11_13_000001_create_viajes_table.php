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
        Schema::create('viajes', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_ruta');
            $table->foreignId('escuela_id')->constrained('escuelas')->onDelete('cascade');
            $table->foreignId('chofer_id')->nullable()->constrained('choferes')->onDelete('set null');
            $table->foreignId('unidad_id')->nullable()->constrained('unidades')->onDelete('set null');
            
            // Horarios de confirmación
            $table->time('hora_inicio_confirmacion'); // Ej: 06:00:00
            $table->time('hora_fin_confirmacion');    // Ej: 06:30:00
            
            // Horarios del viaje
            $table->time('hora_inicio_viaje');        // Hora que inicia el chofer
            $table->time('hora_llegada_estimada');    // Hora estimada de llegada a escuela
            
            // Fecha del viaje
            $table->date('fecha_viaje');
            
            // Coordenadas de recogida (JSON array)
            // [{hijo_id: 1, lat: 25.123, lng: -100.456, direccion: "Calle X", orden: 1}]
            $table->json('coordenadas_recogida')->nullable();
            
            // Estado del viaje
            // pendiente, confirmaciones_abiertas, confirmaciones_cerradas, en_curso, completado, cancelado
            $table->enum('estado', [
                'pendiente',
                'confirmaciones_abiertas',
                'confirmaciones_cerradas',
                'en_curso',
                'completado',
                'cancelado'
            ])->default('pendiente');
            
            // Configuración adicional
            $table->text('notas')->nullable();
            $table->integer('capacidad_maxima')->default(30);
            $table->integer('ninos_confirmados')->default(0);
            
            $table->timestamps();
            
            // Índices
            $table->index('fecha_viaje');
            $table->index('estado');
            $table->index(['escuela_id', 'fecha_viaje']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('viajes');
    }
};

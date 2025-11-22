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
            
            // Relaciones
            $table->foreignId('unidad_id')->constrained('unidades')->onDelete('cascade');
            $table->foreignId('chofer_id')->constrained('choferes')->onDelete('cascade');
            $table->foreignId('escuela_id')->constrained('escuelas')->onDelete('cascade');
            
            // Información básica
            $table->string('nombre_ruta', 100);
            $table->enum('tipo_viaje', ['unico', 'recurrente'])->default('unico');
            
            // Para viajes únicos
            $table->date('fecha_especifica')->nullable();
            
            // Para viajes recurrentes
            $table->json('dias_activos')->nullable(); // ["Lunes", "Martes", "Miércoles"]
            $table->date('fecha_inicio_vigencia')->nullable();
            $table->date('fecha_fin_vigencia')->nullable();
            
            // Información de horario y capacidad
            $table->time('horario_salida');
            $table->integer('capacidad_maxima')->unsigned();
            $table->integer('capacidad_actual')->unsigned()->default(0);
            $table->enum('turno', ['matutino', 'vespertino'])->default('matutino');
            
            // Estado del viaje
            $table->enum('estado', ['abierto', 'cerrado', 'en_curso', 'completado', 'cancelado'])->default('abierto');
            
            // Información adicional
            $table->text('descripcion')->nullable();
            $table->text('notas')->nullable();
            
            $table->timestamps();
            
            // Índices para optimizar consultas
            $table->index(['escuela_id', 'fecha_especifica']);
            $table->index(['escuela_id', 'tipo_viaje', 'estado']);
            $table->index('estado');
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

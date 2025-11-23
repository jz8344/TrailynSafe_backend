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
            
            // Información básica
            $table->string('nombre');
            $table->enum('tipo_viaje', ['unico', 'recurrente']);
            $table->enum('turno', ['matutino', 'vespertino']);
            
            // Fechas y horarios
            $table->date('fecha_viaje')->nullable(); // Para viajes únicos
            $table->date('fecha_inicio_recurrencia')->nullable(); // Para viajes recurrentes
            $table->date('fecha_fin_recurrencia')->nullable(); // Para viajes recurrentes
            $table->json('dias_semana')->nullable(); // ["lunes", "martes", ...] para recurrentes
            $table->time('hora_salida_programada');
            
            // Ventana de confirmaciones
            $table->time('hora_inicio_confirmaciones');
            $table->time('hora_fin_confirmaciones');
            
            // Estado del viaje
            $table->enum('estado', [
                'pendiente',           // Creado por admin, aún no activo
                'programado',          // Aprobado por admin, esperando confirmaciones
                'en_confirmaciones',   // Padres pueden registrar direcciones
                'confirmado',          // Confirmaciones cerradas, listo para k-Means
                'generando_ruta',      // Procesando con k-Means
                'ruta_generada',       // Ruta lista, esperando inicio
                'en_curso',            // Viaje en progreso
                'finalizado',          // Viaje completado
                'cancelado'            // Cancelado
            ])->default('pendiente');
            
            // Relaciones
            $table->foreignId('escuela_id')->constrained('escuelas')->onDelete('restrict');
            $table->foreignId('unidad_id')->constrained('unidades')->onDelete('restrict');
            $table->foreignId('chofer_id')->constrained('choferes')->onDelete('restrict');
            $table->unsignedBigInteger('ruta_id')->nullable(); // Foreign key se agregará en migración posterior
            
            // Cupos y confirmaciones
            $table->integer('cupo_minimo')->default(5);
            $table->integer('cupo_maximo');
            $table->integer('confirmaciones_actuales')->default(0);
            
            // Metadatos
            $table->text('notas')->nullable();
            $table->foreignId('admin_creador_id')->nullable()->constrained('admins')->onDelete('set null');
            $table->timestamp('fecha_cambio_estado')->nullable();
            $table->text('motivo_cancelacion')->nullable();
            
            $table->timestamps();
            
            // Índices para mejorar rendimiento
            $table->index('estado');
            $table->index('fecha_viaje');
            $table->index('tipo_viaje');
            $table->index(['escuela_id', 'turno', 'estado']);
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

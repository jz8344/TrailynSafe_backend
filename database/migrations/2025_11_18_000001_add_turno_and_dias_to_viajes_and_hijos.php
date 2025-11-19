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
        // Agregar turno a hijos
        Schema::table('hijos', function (Blueprint $table) {
            $table->enum('turno', ['matutino', 'vespertino'])->default('matutino')->after('escuela_id');
        });

        // Agregar campos a viajes
        Schema::table('viajes', function (Blueprint $table) {
            $table->enum('turno', ['matutino', 'vespertino'])->default('matutino')->after('escuela_id');
            $table->enum('tipo_viaje', ['ida', 'retorno'])->default('ida')->after('turno');
            $table->foreignId('viaje_retorno_id')->nullable()->constrained('viajes')->onDelete('set null')->after('tipo_viaje');
            
            // Días de la semana que aplica el viaje (JSON array)
            $table->json('dias_semana')->nullable()->after('fecha_viaje')->comment('["lunes","martes","miercoles","jueves","viernes"]');
            
            // Fecha fin para viajes recurrentes
            $table->date('fecha_fin')->nullable()->after('dias_semana');
            
            // Confirmación automática
            $table->boolean('confirmacion_automatica')->default(false)->after('capacidad_maxima');
        });

        // Agregar ubicación guardada a confirmaciones
        Schema::table('confirmaciones_viaje', function (Blueprint $table) {
            $table->boolean('ubicacion_automatica')->default(false)->after('direccion_recogida')->comment('Si usó ubicación guardada');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hijos', function (Blueprint $table) {
            $table->dropColumn('turno');
        });

        Schema::table('viajes', function (Blueprint $table) {
            $table->dropForeign(['viaje_retorno_id']);
            $table->dropColumn([
                'turno',
                'tipo_viaje',
                'viaje_retorno_id',
                'dias_semana',
                'fecha_fin',
                'confirmacion_automatica'
            ]);
        });

        Schema::table('confirmaciones_viaje', function (Blueprint $table) {
            $table->dropColumn('ubicacion_automatica');
        });
    }
};

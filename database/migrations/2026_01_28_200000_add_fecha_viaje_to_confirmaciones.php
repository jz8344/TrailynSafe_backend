<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega campo fecha_viaje a confirmaciones_viaje
     * Esto permite saber PARA QUÉ DÍA específico es cada confirmación
     * (especialmente importante para viajes recurrentes)
     */
    public function up(): void
    {
        Schema::table('confirmaciones_viaje', function (Blueprint $table) {
            // Fecha específica del viaje para esta confirmación
            // Ej: El padre confirma para el viaje del día 28/01/2026
            $table->date('fecha_viaje')->nullable()->after('viaje_id');
            
            // Índice para búsquedas rápidas por fecha
            $table->index(['viaje_id', 'fecha_viaje']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('confirmaciones_viaje', function (Blueprint $table) {
            $table->dropIndex(['viaje_id', 'fecha_viaje']);
            $table->dropColumn('fecha_viaje');
        });
    }
};

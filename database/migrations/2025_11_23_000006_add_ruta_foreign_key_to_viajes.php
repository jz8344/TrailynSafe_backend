<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Agregar foreign keys después de que ambas tablas (viajes y rutas) existan
     */
    public function up(): void
    {
        // Foreign key: viajes -> rutas
        Schema::table('viajes', function (Blueprint $table) {
            $table->foreign('ruta_id')
                  ->references('id')
                  ->on('rutas')
                  ->onDelete('set null');
        });

        // Foreign key: rutas -> viajes (con unique para relación 1:1)
        Schema::table('rutas', function (Blueprint $table) {
            $table->foreign('viaje_id')
                  ->references('id')
                  ->on('viajes')
                  ->onDelete('cascade');
            $table->unique('viaje_id'); // Relación 1:1
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rutas', function (Blueprint $table) {
            $table->dropForeign(['viaje_id']);
            $table->dropUnique(['viaje_id']);
        });

        Schema::table('viajes', function (Blueprint $table) {
            $table->dropForeign(['ruta_id']);
        });
    }
};

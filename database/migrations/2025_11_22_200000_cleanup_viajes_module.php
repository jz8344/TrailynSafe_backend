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
        // Eliminar tablas dependientes primero (identificadas por error de FK)
        Schema::dropIfExists('viaje_solicitudes');
        Schema::dropIfExists('viajes');

        // Eliminar tablas relacionadas con el módulo de viajes si existen
        Schema::dropIfExists('viajes');
        Schema::dropIfExists('rutas');
        Schema::dropIfExists('confirmaciones');
        Schema::dropIfExists('viaje_hijo'); // Posible tabla pivote

        // Eliminar columnas relacionadas en la tabla hijos
        if (Schema::hasColumn('hijos', 'turno')) {
            Schema::table('hijos', function (Blueprint $table) {
                $table->dropColumn('turno');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Recrear columna turno en hijos
        if (!Schema::hasColumn('hijos', 'turno')) {
            Schema::table('hijos', function (Blueprint $table) {
                $table->enum('turno', ['matutino', 'vespertino'])->default('matutino')->after('escuela_id');
            });
        }

        // Nota: No recreamos las tablas completas de viajes aquí porque 
        // no tenemos la definición original exacta en este contexto de limpieza.
    }
};

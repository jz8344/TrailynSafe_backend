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

        // Agregar ubicación automática a confirmaciones
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

        Schema::table('confirmaciones_viaje', function (Blueprint $table) {
            $table->dropColumn('ubicacion_automatica');
        });
    }
};

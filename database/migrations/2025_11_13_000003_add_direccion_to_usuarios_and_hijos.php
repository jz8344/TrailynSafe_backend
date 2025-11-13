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
        Schema::table('usuarios', function (Blueprint $table) {
            $table->string('direccion', 500)->nullable()->after('telefono');
            $table->decimal('latitud_default', 10, 8)->nullable()->after('direccion');
            $table->decimal('longitud_default', 11, 8)->nullable()->after('latitud_default');
            $table->string('ciudad', 100)->nullable()->after('longitud_default');
            $table->string('estado', 100)->nullable()->after('ciudad');
            $table->string('codigo_postal', 10)->nullable()->after('estado');
        });

        Schema::table('hijos', function (Blueprint $table) {
            // Dirección específica del hijo (si es diferente a la del padre)
            $table->string('direccion_alterna', 500)->nullable()->after('escuela_id');
            $table->decimal('latitud_alterna', 10, 8)->nullable()->after('direccion_alterna');
            $table->decimal('longitud_alterna', 11, 8)->nullable()->after('latitud_alterna');
            $table->text('notas_direccion')->nullable()->after('longitud_alterna')
                ->comment('Referencias adicionales para ubicar el domicilio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn([
                'direccion',
                'latitud_default',
                'longitud_default',
                'ciudad',
                'estado',
                'codigo_postal'
            ]);
        });

        Schema::table('hijos', function (Blueprint $table) {
            $table->dropColumn([
                'direccion_alterna',
                'latitud_alterna',
                'longitud_alterna',
                'notas_direccion'
            ]);
        });
    }
};

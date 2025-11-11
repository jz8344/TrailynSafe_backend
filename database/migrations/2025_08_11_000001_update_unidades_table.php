<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->string('descripcion')->nullable()->after('matricula');
            $table->string('marca')->nullable()->after('descripcion');
            $table->year('anio')->nullable()->after('marca');
            $table->string('color')->nullable()->after('anio');
            $table->string('imagen')->nullable()->after('color');
            $table->enum('estado', ['activo', 'en_ruta', 'mantenimiento', 'inactivo'])->default('activo')->after('imagen');
            $table->string('numero_serie')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('unidades', function (Blueprint $table) {
            $table->dropColumn([
                'descripcion', 
                'marca', 
                'año', 
                'color', 
                'imagen', 
                'estado', 
                'numero_serie'
            ]);
        });
    }
};

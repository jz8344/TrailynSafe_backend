<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('choferes', function (Blueprint $table) {
            $table->dropForeign(['usuario_id']);
            $table->dropColumn(['usuario_id', 'licencia']);
            
            $table->string('nombre', 100)->after('id');
            $table->string('apellidos', 100)->after('nombre');
            $table->string('numero_licencia', 50)->nullable()->after('apellidos');
            $table->string('curp', 18)->nullable()->after('numero_licencia');
            $table->string('telefono', 15)->nullable()->after('curp');
            $table->string('correo', 100)->nullable()->after('telefono');
            $table->enum('estado', ['disponible', 'en_ruta', 'no_activo'])->default('disponible')->after('correo');
        });
    }

    public function down(): void
    {
        Schema::table('choferes', function (Blueprint $table) {
            $table->dropColumn([
                'nombre', 
                'apellidos', 
                'numero_licencia', 
                'curp', 
                'telefono', 
                'correo', 
                'estado'
            ]);
            
            $table->unsignedBigInteger('usuario_id')->after('id');
            $table->string('licencia')->nullable()->after('usuario_id');
            $table->foreign('usuario_id')->references('id')->on('usuarios')->onDelete('cascade');
        });
    }
};

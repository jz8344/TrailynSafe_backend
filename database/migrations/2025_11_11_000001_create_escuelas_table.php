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
        Schema::create('escuelas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('clave')->nullable(); // CCT o código
            $table->enum('nivel', ['preescolar', 'primaria', 'secundaria', 'preparatoria', 'universidad', 'mixto']);
            $table->enum('turno', ['matutino', 'vespertino', 'mixto', 'tiempo_completo'])->nullable();
            $table->text('direccion');
            $table->string('colonia')->nullable();
            $table->string('ciudad')->nullable();
            $table->string('codigo_postal', 5)->nullable();
            $table->string('telefono')->nullable();
            $table->string('correo')->nullable();
            $table->string('contacto')->nullable(); // Nombre del contacto
            $table->string('cargo_contacto')->nullable(); // Cargo del contacto
            $table->time('horario_entrada')->nullable();
            $table->time('horario_salida')->nullable();
            $table->date('fecha_inicio_servicio')->nullable();
            $table->integer('numero_alumnos')->nullable();
            $table->text('notas')->nullable();
            $table->enum('estado', ['activo', 'inactivo', 'suspendido'])->default('activo');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escuelas');
    }
};

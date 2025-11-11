<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('rutas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->unsignedBigInteger('chofer_id');
            $table->unsignedBigInteger('unidad_id');
            $table->string('horario')->nullable();
            $table->string('inicio')->nullable();
            $table->string('fin')->nullable();
            $table->unsignedInteger('rango')->default(0);
            $table->enum('estado', ['activa','inactiva','completada'])->default('inactiva');
            $table->timestamps();
            $table->foreign('chofer_id')->references('id')->on('choferes')->onDelete('cascade');
            $table->foreign('unidad_id')->references('id')->on('unidades')->onDelete('cascade');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('rutas');
    }
};

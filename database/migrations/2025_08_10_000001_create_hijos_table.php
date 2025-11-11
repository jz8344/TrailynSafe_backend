<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('hijos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre');
            $table->string('grado')->nullable();
            $table->string('grupo')->nullable();
            $table->string('codigo_qr')->unique();
            $table->unsignedBigInteger('padre_id');
            $table->timestamps();
            $table->foreign('padre_id')->references('id')->on('usuarios')->onDelete('cascade');
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('hijos');
    }
};

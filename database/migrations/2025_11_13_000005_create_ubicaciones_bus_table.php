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
        Schema::create('ubicaciones_bus', function (Blueprint $table) {
            $table->id();
            $table->foreignId('viaje_id')->constrained('viajes')->onDelete('cascade');
            $table->foreignId('unidad_id')->constrained('unidades')->onDelete('cascade');
            
            // Coordenadas GPS del módulo SIM800
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            $table->decimal('velocidad', 5, 2)->nullable(); // km/h
            $table->decimal('heading', 5, 2)->nullable(); // Dirección en grados (0-360)
            
            // Precisión del GPS
            $table->decimal('precision', 5, 2)->nullable(); // metros
            
            // Timestamp del GPS
            $table->timestamp('timestamp_gps');
            
            $table->timestamps();
            
            // Índices para consultas rápidas
            $table->index('viaje_id');
            $table->index('unidad_id');
            $table->index('timestamp_gps');
            $table->index(['viaje_id', 'timestamp_gps']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ubicaciones_bus');
    }
};

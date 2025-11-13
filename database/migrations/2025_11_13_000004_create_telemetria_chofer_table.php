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
        Schema::create('telemetria_chofer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chofer_id')->constrained('choferes')->onDelete('cascade');
            $table->foreignId('viaje_id')->nullable()->constrained('viajes')->onDelete('set null');
            
            // Datos del wearOS
            $table->integer('frecuencia_cardiaca')->nullable();
            $table->decimal('velocidad', 5, 2)->nullable(); // km/h
            $table->decimal('aceleracion', 5, 2)->nullable(); // m/s²
            
            // Datos del GPS (ESP32)
            $table->decimal('latitud', 10, 8)->nullable();
            $table->decimal('longitud', 11, 8)->nullable();
            $table->decimal('altitud', 6, 2)->nullable(); // metros
            
            // Sensores del bus (ESP32)
            $table->boolean('impacto_detectado')->default(false);
            $table->decimal('temperatura', 5, 2)->nullable(); // °C
            $table->decimal('nivel_combustible', 5, 2)->nullable(); // %
            
            // Alertas y estados
            $table->enum('nivel_alerta', ['normal', 'precaucion', 'peligro'])->default('normal');
            $table->text('descripcion_alerta')->nullable();
            
            // Timestamp del sensor
            $table->timestamp('timestamp_lectura');
            
            $table->timestamps();
            
            // Índices
            $table->index('chofer_id');
            $table->index('viaje_id');
            $table->index('timestamp_lectura');
            $table->index(['chofer_id', 'timestamp_lectura']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('telemetria_chofer');
    }
};

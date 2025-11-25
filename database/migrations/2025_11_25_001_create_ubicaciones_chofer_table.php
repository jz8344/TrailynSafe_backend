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
        Schema::create('ubicaciones_chofer', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chofer_id')->constrained('choferes')->onDelete('cascade');
            $table->foreignId('ruta_id')->nullable()->constrained('rutas')->onDelete('set null');
            $table->foreignId('viaje_id')->nullable()->constrained('viajes')->onDelete('set null');
            $table->decimal('latitud', 10, 8);
            $table->decimal('longitud', 11, 8);
            $table->decimal('velocidad', 6, 2)->default(0)->comment('Velocidad en m/s');
            $table->decimal('heading', 5, 2)->default(0)->comment('Dirección en grados 0-360');
            $table->decimal('accuracy', 8, 2)->default(0)->comment('Precisión GPS en metros');
            $table->integer('battery_level')->default(100)->comment('Nivel de batería 0-100');
            $table->timestamp('timestamp')->useCurrent();
            $table->timestamps();
            
            // Índices para búsquedas rápidas
            $table->index('chofer_id');
            $table->index('ruta_id');
            $table->index('viaje_id');
            $table->index('timestamp');
            $table->index(['chofer_id', 'timestamp']);
            $table->index(['ruta_id', 'timestamp']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ubicaciones_chofer');
    }
};

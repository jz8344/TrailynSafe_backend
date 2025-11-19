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
        Schema::table('viajes', function (Blueprint $table) {
            // Hacer campos de confirmación nullable para viajes de retorno
            // que no requieren confirmación de padres
            $table->time('hora_inicio_confirmacion')->nullable()->change();
            $table->time('hora_fin_confirmacion')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->time('hora_inicio_confirmacion')->nullable(false)->change();
            $table->time('hora_fin_confirmacion')->nullable(false)->change();
        });
    }
};

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
            $table->decimal('latitud_inicio_chofer', 10, 8)->nullable()->after('motivo_cancelacion');
            $table->decimal('longitud_inicio_chofer', 11, 8)->nullable()->after('latitud_inicio_chofer');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->dropColumn(['latitud_inicio_chofer', 'longitud_inicio_chofer']);
        });
    }
};

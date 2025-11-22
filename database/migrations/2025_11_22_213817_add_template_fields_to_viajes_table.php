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
            $table->boolean('es_plantilla')->default(false)->after('estado');
            $table->unsignedBigInteger('parent_viaje_id')->nullable()->after('es_plantilla');
            $table->foreign('parent_viaje_id')->references('id')->on('viajes')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('viajes', function (Blueprint $table) {
            $table->dropForeign(['parent_viaje_id']);
            $table->dropColumn(['es_plantilla', 'parent_viaje_id']);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('hijos', function (Blueprint $table) {
            $table->string('emergencia_1', 25)->nullable()->after('padre_id');
            $table->string('emergencia_2', 25)->nullable()->after('emergencia_1');
        });
    }

    public function down(): void
    {
        Schema::table('hijos', function (Blueprint $table) {
            $table->dropColumn(['emergencia_1','emergencia_2']);
        });
    }
};

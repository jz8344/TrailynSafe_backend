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
        Schema::table('hijos', function (Blueprint $table) {
            if (!Schema::hasColumn('hijos', 'escuela_id')) {
                $table->unsignedBigInteger('escuela_id')->nullable()->after('escuela');
                $table->foreign('escuela_id')->references('id')->on('escuelas')->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('hijos', function (Blueprint $table) {
            if (Schema::hasColumn('hijos', 'escuela_id')) {
                $table->dropForeign(['escuela_id']);
                $table->dropColumn('escuela_id');
            }
        });
    }
};

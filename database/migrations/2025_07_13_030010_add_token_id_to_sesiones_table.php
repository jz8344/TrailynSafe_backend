<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sesiones') && !Schema::hasColumn('sesiones', 'token_id')) {
            Schema::table('sesiones', function (Blueprint $table) {
                $table->unsignedBigInteger('token_id')->nullable()->after('user_id');
                $table->index('token_id');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sesiones') && Schema::hasColumn('sesiones', 'token_id')) {
            Schema::table('sesiones', function (Blueprint $table) {
                $table->dropIndex(['token_id']);
                $table->dropColumn('token_id');
            });
        }
    }
};
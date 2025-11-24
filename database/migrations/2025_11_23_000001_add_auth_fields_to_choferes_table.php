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
        Schema::table('choferes', function (Blueprint $table) {
            // Solo agregar los campos que faltan
            if (!Schema::hasColumn('choferes', 'password')) {
                $table->string('password')->nullable()->after('correo');
            }
            if (!Schema::hasColumn('choferes', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('estado');
            }
            if (!Schema::hasColumn('choferes', 'remember_token')) {
                $table->rememberToken()->after('email_verified_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('choferes', function (Blueprint $table) {
            $table->dropColumn([
                'password',
                'email_verified_at',
                'remember_token'
            ]);
        });
    }
};

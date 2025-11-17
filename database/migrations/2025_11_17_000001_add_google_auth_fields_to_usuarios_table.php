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
        Schema::table('usuarios', function (Blueprint $table) {
            // Campos para Google Authentication
            $table->string('google_id')->nullable()->unique()->after('correo');
            $table->string('avatar')->nullable()->after('google_id');
            $table->string('auth_provider')->default('local')->after('avatar'); // 'local', 'google'
            $table->boolean('email_verified')->default(false)->after('auth_provider');
            
            // Hacer password nullable para usuarios que solo usan Google
            $table->string('contrasena')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('usuarios', function (Blueprint $table) {
            $table->dropColumn(['google_id', 'avatar', 'auth_provider', 'email_verified']);
            $table->string('contrasena')->nullable(false)->change();
        });
    }
};

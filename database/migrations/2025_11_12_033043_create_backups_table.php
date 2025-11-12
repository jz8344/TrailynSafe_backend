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
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique(); // Nombre del archivo
            $table->enum('tipo', ['completo', 'tablas', 'estructura'])->default('completo');
            $table->enum('formato', ['sql', 'gz', 'zip'])->default('sql');
            $table->json('tablas')->nullable(); // Array de tablas incluidas
            $table->bigInteger('tamano')->default(0); // Tamaño en bytes
            $table->string('ruta'); // Ruta del archivo
            $table->text('descripcion')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};

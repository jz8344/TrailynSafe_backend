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
        Schema::create('notificaciones_panel', function (Blueprint $table) {
            $table->id();
            $table->string('titulo');
            $table->text('mensaje');
            $table->enum('tipo', ['success', 'info', 'warning', 'danger'])->default('info');
            $table->string('entity_type')->nullable(); // 'escuela', 'unidad', etc.
            $table->string('entity_id')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable(); // Quién generó la acción (opcional)
            $table->string('admin_name')->nullable(); // Nombre del admin para mostrar rápido
            $table->boolean('read')->default(false);
            $table->timestamps();

            // Índices para búsquedas rápidas
            $table->index('created_at');
            $table->index('read');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notificaciones_panel');
    }
};

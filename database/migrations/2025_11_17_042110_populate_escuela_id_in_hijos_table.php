<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Obtener todos los hijos que tienen escuela (texto) pero escuela_id es null
        $hijos = DB::table('hijos')
            ->whereNotNull('escuela')
            ->whereNull('escuela_id')
            ->get();

        foreach ($hijos as $hijo) {
            // Buscar la escuela por nombre (con LIKE para coincidencias parciales)
            $escuela = DB::table('escuelas')
                ->where('nombre', 'like', '%' . $hijo->escuela . '%')
                ->first();

            if ($escuela) {
                // Actualizar escuela_id
                DB::table('hijos')
                    ->where('id', $hijo->id)
                    ->update(['escuela_id' => $escuela->id]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Opcional: resetear escuela_id a null si se revierte
        DB::table('hijos')
            ->whereNotNull('escuela_id')
            ->update(['escuela_id' => null]);
    }
};

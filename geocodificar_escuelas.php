<?php

/**
 * Script para geocodificar todas las escuelas existentes
 * 
 * Este script actualiza todas las escuelas que no tienen coordenadas,
 * utilizando el servicio de geocodificación automática.
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\Escuela;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n========================================\n";
echo "GEOCODIFICAR ESCUELAS\n";
echo "========================================\n\n";

try {
    $escuelas = Escuela::all();
    
    echo "Total de escuelas: " . $escuelas->count() . "\n\n";
    
    $actualizadas = 0;
    $sinCoordenadas = 0;
    $yaConCoordenadas = 0;
    $errores = 0;
    
    foreach ($escuelas as $escuela) {
        echo "---\n";
        echo "ID {$escuela->id}: {$escuela->nombre}\n";
        echo "Dirección: {$escuela->direccion}\n";
        
        // Verificar si ya tiene coordenadas válidas
        if (!empty($escuela->latitud) && !empty($escuela->longitud) && 
            $escuela->latitud != 0 && $escuela->longitud != 0) {
            echo "✅ Ya tiene coordenadas: {$escuela->latitud}, {$escuela->longitud}\n";
            $yaConCoordenadas++;
            continue;
        }
        
        echo "⏳ Geocodificando...\n";
        
        try {
            // Forzar actualización para que el observer geocodifique
            $escuela->direccion = trim($escuela->direccion);
            $escuela->save();
            
            // Recargar para ver cambios
            $escuela->refresh();
            
            if (!empty($escuela->latitud) && !empty($escuela->longitud)) {
                echo "✅ Coordenadas obtenidas: {$escuela->latitud}, {$escuela->longitud}\n";
                echo "🔗 Ver en Maps: https://www.google.com/maps?q={$escuela->latitud},{$escuela->longitud}\n";
                $actualizadas++;
            } else {
                echo "⚠️  No se pudieron obtener coordenadas\n";
                $sinCoordenadas++;
            }
            
            // Esperar 200ms entre requests para no saturar la API
            usleep(200000);
            
        } catch (\Exception $e) {
            echo "❌ Error: {$e->getMessage()}\n";
            $errores++;
        }
    }
    
    echo "\n========================================\n";
    echo "RESUMEN\n";
    echo "========================================\n";
    echo "✅ Actualizadas: {$actualizadas}\n";
    echo "📍 Ya tenían coordenadas: {$yaConCoordenadas}\n";
    echo "⚠️  Sin coordenadas: {$sinCoordenadas}\n";
    echo "❌ Errores: {$errores}\n";
    echo "========================================\n\n";
    
    if ($sinCoordenadas > 0) {
        echo "⚠️  NOTA: Las escuelas sin coordenadas pueden tener direcciones incorrectas.\n";
        echo "   Verifica manualmente y actualiza la dirección si es necesario.\n\n";
    }
    
    echo "🎉 Proceso completado.\n";
    echo "Ahora todas las nuevas escuelas obtendrán coordenadas automáticamente.\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error fatal: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

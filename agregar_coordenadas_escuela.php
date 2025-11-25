<?php

/**
 * Script para agregar coordenadas a escuelas
 * 
 * Las coordenadas son necesarias para:
 * - Algoritmo k-Means (punto final de la ruta)
 * - Mostrar ubicación en mapas
 * - Calcular distancias y tiempos
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n========================================\n";
echo "AGREGAR COORDENADAS A ESCUELAS\n";
echo "========================================\n\n";

try {
    // Coordenadas del COBAEJ 21 (Google Maps)
    $escuelaId = 2;
    $latitud = 20.5954738;
    $longitud = -103.4094459;
    
    $escuela = DB::table('escuelas')->where('id', $escuelaId)->first();
    
    if (!$escuela) {
        echo "❌ Escuela ID {$escuelaId} no encontrada\n";
        exit(1);
    }
    
    echo "Escuela encontrada:\n";
    echo "ID: {$escuela->id}\n";
    echo "Nombre: {$escuela->nombre}\n";
    echo "Dirección: {$escuela->direccion}\n\n";
    
    // Verificar si ya tiene coordenadas
    if (isset($escuela->latitud) && isset($escuela->longitud) && 
        $escuela->latitud && $escuela->longitud) {
        echo "✅ La escuela ya tiene coordenadas:\n";
        echo "   Latitud: {$escuela->latitud}\n";
        echo "   Longitud: {$escuela->longitud}\n\n";
        echo "¿Deseas actualizarlas? (Presiona Enter para continuar o Ctrl+C para cancelar)\n";
        fgets(STDIN);
    }
    
    // Actualizar coordenadas
    $updated = DB::table('escuelas')
        ->where('id', $escuelaId)
        ->update([
            'latitud' => $latitud,
            'longitud' => $longitud,
            'updated_at' => now()
        ]);
    
    if ($updated) {
        echo "✅ Coordenadas actualizadas exitosamente:\n";
        echo "   Latitud: {$latitud}\n";
        echo "   Longitud: {$longitud}\n\n";
        
        // Verificar actualización
        $escuelaActualizada = DB::table('escuelas')->where('id', $escuelaId)->first();
        echo "Verificación:\n";
        echo "   Latitud almacenada: {$escuelaActualizada->latitud}\n";
        echo "   Longitud almacenada: {$escuelaActualizada->longitud}\n\n";
        
        echo "🔗 Ver en Google Maps:\n";
        echo "   https://www.google.com/maps?q={$latitud},{$longitud}\n\n";
        
        echo "✅ Ahora el sistema puede:\n";
        echo "   - Generar rutas con k-Means usando esta escuela como destino\n";
        echo "   - Mostrar ubicación en mapas\n";
        echo "   - Calcular distancias desde confirmaciones\n\n";
    } else {
        echo "⚠️  No se realizaron cambios (los datos son idénticos)\n\n";
    }
    
    // Listar todas las escuelas y su estado de coordenadas
    echo "========================================\n";
    echo "RESUMEN DE ESCUELAS\n";
    echo "========================================\n\n";
    
    $escuelas = DB::table('escuelas')->get();
    
    foreach ($escuelas as $esc) {
        $tieneCoords = (isset($esc->latitud) && isset($esc->longitud) && 
                       $esc->latitud && $esc->longitud);
        $status = $tieneCoords ? '✅' : '❌';
        
        echo "{$status} ID {$esc->id}: {$esc->nombre}\n";
        if ($tieneCoords) {
            echo "   📍 {$esc->latitud}, {$esc->longitud}\n";
        } else {
            echo "   ⚠️  Sin coordenadas\n";
        }
        echo "\n";
    }
    
    echo "========================================\n";
    echo "SIGUIENTE PASO\n";
    echo "========================================\n\n";
    echo "1. Verificar que el viaje de prueba (ID 2) use esta escuela\n";
    echo "2. En app chofer: abrir confirmaciones\n";
    echo "3. En app padres: confirmar ubicaciones\n";
    echo "4. En app chofer: cerrar confirmaciones\n";
    echo "5. En app chofer: confirmar viaje (genera ruta con k-Means)\n";
    echo "6. Django procesará y enviará ruta optimizada\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

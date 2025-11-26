<?php

/**
 * Script para regenerar polylines de rutas existentes
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Ruta;
use App\Services\RutaOptimizacionService;
use Illuminate\Support\Facades\Log;

echo "🔄 Regenerando polylines de rutas existentes\n";
echo "=============================================\n\n";

// Obtener todas las rutas sin polyline o con polyline vacío
$rutas = Ruta::with(['viaje.escuela', 'paradas' => function($query) {
    $query->orderBy('orden');
}])
->where(function($query) {
    $query->whereNull('polyline')
          ->orWhere('polyline', '');
})
->get();

echo "📊 Total de rutas a actualizar: " . $rutas->count() . "\n\n";

if ($rutas->isEmpty()) {
    echo "✅ Todas las rutas ya tienen polyline\n";
    exit(0);
}

$service = new RutaOptimizacionService();
$actualizadas = 0;
$errores = 0;

foreach ($rutas as $ruta) {
    echo "🔧 Procesando Ruta ID: {$ruta->id}\n";
    echo "   Viaje: {$ruta->viaje->id}\n";
    echo "   Escuela: {$ruta->viaje->escuela->nombre}\n";
    echo "   Paradas: " . $ruta->paradas->count() . "\n";
    
    try {
        // Preparar datos
        $escuela = [
            'lat' => floatval($ruta->viaje->escuela->latitud),
            'lng' => floatval($ruta->viaje->escuela->longitud)
        ];
        
        $paradas = $ruta->paradas->map(function($parada) {
            return [
                'latitud' => floatval($parada->latitud),
                'longitud' => floatval($parada->longitud),
                'direccion' => $parada->direccion
            ];
        })->toArray();
        
        if (empty($paradas)) {
            echo "   ⚠️  Sin paradas, saltando...\n\n";
            continue;
        }
        
        // Generar polyline
        echo "   🚀 Generando polyline...\n";
        
        // Usar el método privado a través de reflexión
        $reflection = new ReflectionClass($service);
        $method = $reflection->getMethod('generarPolylineGoogleMaps');
        $method->setAccessible(true);
        
        $polylineData = $method->invoke($service, $escuela, $paradas);
        
        if (!empty($polylineData['polyline'])) {
            // Actualizar ruta
            $ruta->polyline = $polylineData['polyline'];
            $ruta->save();
            
            echo "   ✅ Polyline actualizada: " . strlen($polylineData['polyline']) . " caracteres\n";
            echo "   Preview: " . substr($polylineData['polyline'], 0, 50) . "...\n\n";
            
            $actualizadas++;
        } else {
            echo "   ❌ No se pudo generar polyline\n\n";
            $errores++;
        }
        
    } catch (\Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n\n";
        $errores++;
    }
}

echo "\n==========================================\n";
echo "📈 Resumen:\n";
echo "   - Rutas actualizadas: $actualizadas\n";
echo "   - Errores: $errores\n";
echo "   - Total procesadas: " . ($actualizadas + $errores) . "\n";
echo "==========================================\n";

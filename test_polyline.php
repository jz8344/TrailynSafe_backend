<?php

/**
 * Script de prueba para generar polyline
 */

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Services\RutaOptimizacionService;
use Illuminate\Support\Facades\Log;

echo "🧪 Test de generación de Polyline\n";
echo "==================================\n\n";

// Datos de prueba
$escuela = [
    'lat' => 20.6597,
    'lng' => -103.3496
];

$paradas = [
    [
        'confirmacion_id' => 1,
        'hijo_id' => 1,
        'hijo_nombre' => 'Test 1',
        'direccion_recogida' => 'Zaragoza 23, 45660 San Miguel Cuyutlán, Jal., México',
        'latitud' => 20.41861954,
        'longitud' => -103.39288082,
        'coordenadas' => [
            'lat' => 20.41861954,
            'lng' => -103.39288082
        ]
    ],
    [
        'confirmacion_id' => 2,
        'hijo_id' => 2,
        'hijo_nombre' => 'Test 2',
        'direccion_recogida' => 'Independencia 4A, 24630 San Miguel Cuyutlán, Jal., México',
        'latitud' => 20.41608919,
        'longitud' => -103.39107502,
        'coordenadas' => [
            'lat' => 20.41608919,
            'lng' => -103.39107502
        ]
    ],
    [
        'confirmacion_id' => 3,
        'hijo_id' => 3,
        'hijo_nombre' => 'Test 3',
        'direccion_recogida' => 'Galeana 35, 45660 San Miguel Cuyutlán, Jal., México',
        'latitud' => 20.41331873,
        'longitud' => -103.38915129,
        'coordenadas' => [
            'lat' => 20.41331873,
            'lng' => -103.38915129
        ]
    ]
];

echo "📍 Escuela: [{$escuela['lat']}, {$escuela['lng']}]\n";
echo "📍 Total paradas: " . count($paradas) . "\n\n";

// Crear servicio
$service = new RutaOptimizacionService();

// Simular datos de confirmaciones para el servicio
$confirmaciones = array_map(function($parada) {
    return [
        'id' => $parada['confirmacion_id'],
        'hijo_id' => $parada['hijo_id'],
        'hijo_nombre' => $parada['hijo_nombre'],
        'direccion_recogida' => $parada['direccion_recogida'],
        'latitud' => $parada['latitud'],
        'longitud' => $parada['longitud']
    ];
}, $paradas);

echo "🚀 Generando ruta optimizada...\n\n";

$resultado = $service->optimizarRuta($escuela, $confirmaciones);

if ($resultado['success']) {
    echo "✅ Ruta generada exitosamente\n\n";
    echo "📊 Resumen:\n";
    echo "   - Paradas ordenadas: " . count($resultado['paradas_ordenadas']) . "\n";
    echo "   - Clusters: " . $resultado['num_clusters'] . "\n";
    echo "   - Distancia total: " . $resultado['distancia_total_km'] . " km\n";
    echo "   - Tiempo total: " . $resultado['tiempo_total_min'] . " min\n\n";
    
    echo "🗺️  Polyline:\n";
    if (!empty($resultado['polyline'])) {
        echo "   - Longitud: " . strlen($resultado['polyline']) . " caracteres\n";
        echo "   - Preview: " . substr($resultado['polyline'], 0, 80) . "...\n";
        echo "   - Tiene polyline: SÍ ✅\n";
    } else {
        echo "   - Polyline: VACÍO ❌\n";
        echo "   - Problema: No se generó polyline\n";
    }
    
    echo "\n📝 Paradas en orden:\n";
    foreach ($resultado['paradas_ordenadas'] as $i => $parada) {
        echo "   " . ($i + 1) . ". {$parada['hijo_nombre']} - Cluster {$parada['cluster_asignado']}\n";
        echo "      {$parada['direccion']}\n";
        echo "      Distancia: {$parada['distancia_desde_anterior_km']} km\n";
    }
} else {
    echo "❌ Error: " . $resultado['error'] . "\n";
}

echo "\n📋 Revisa los logs en storage/logs/laravel.log\n";

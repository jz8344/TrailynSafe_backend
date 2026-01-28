<?php
// Ejecutar desde backend/: php scripts/check_data.php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Viaje;
use App\Models\ConfirmacionViaje;
use App\Models\Asistencia;
use App\Models\Chofer;

echo "\n===================================\n";
echo "ANÁLISIS DE DATOS PARA K-MEANS\n";
echo "===================================\n\n";

// Resumen
echo "📊 RESUMEN:\n";
echo "  Choferes: " . Chofer::count() . "\n";
echo "  Viajes: " . Viaje::count() . "\n";
echo "  Confirmaciones: " . ConfirmacionViaje::count() . "\n";
echo "  Asistencias: " . Asistencia::count() . "\n\n";

// Viajes con datos
echo "📋 VIAJES CON DATOS:\n";
$viajes = Viaje::with(['confirmaciones', 'asistencias', 'chofer'])->get();

foreach ($viajes as $v) {
    $confirmaciones = $v->confirmaciones->count();
    $asistencias = $v->asistencias->count();
    $tasa = $confirmaciones > 0 ? round(($asistencias / $confirmaciones) * 100, 2) : 0;
    
    $choferNombre = $v->chofer ? "{$v->chofer->nombre} {$v->chofer->apellidos}" : "N/A";
    
    echo sprintf(
        "  Viaje #%d (Chofer #%d - %s):\n    %d confirmaciones, %d asistencias (%.2f%% tasa)\n\n",
        $v->id,
        $v->chofer_id,
        $choferNombre,
        $confirmaciones,
        $asistencias,
        $tasa
    );
}

// Recomendación
echo "\n===================================\n";
echo "🎯 RECOMENDACIÓN:\n";
echo "===================================\n\n";

$asistenciasTotal = Asistencia::count();

if ($asistenciasTotal == 0) {
    echo "⚠️  NO HAY ASISTENCIAS REGISTRADAS\n\n";
    echo "El análisis K-means necesita:\n";
    echo "  - Tasa de asistencia (asistencias / confirmaciones)\n";
    echo "  - Eficiencia (tiempo real / tiempo estimado)\n";
    echo "  - Tiempo promedio de recogida\n\n";
    echo "Por ahora, el servicio Flask usará DATOS SIMULADOS\n";
    echo "que representan 3 tipos de conductores:\n";
    echo "  ✅ Excelente: 95% asistencia, 105% eficiencia\n";
    echo "  ⚠️  Promedio: 80% asistencia, 95% eficiencia\n";
    echo "  🔴 Requiere Atención: 65% asistencia, 75% eficiencia\n\n";
} else {
    echo "✅ HAY DATOS REALES!\n\n";
    echo "Se pueden calcular métricas reales para K-means\n";
}

echo "===================================\n\n";

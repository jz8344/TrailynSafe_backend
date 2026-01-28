<?php
/**
 * Script para ejecutar en Laravel Tinker
 * Inspeccionar datos existentes en la BD para K-means
 * 
 * Ejecutar: php artisan tinker
 * Luego: include 'scripts/inspect_data_for_kmeans.php';
 */

use App\Models\Viaje;
use App\Models\ConfirmacionViaje;
use App\Models\Asistencia;
use App\Models\Chofer;

echo "\n====================================\n";
echo "INSPECCIÓN DE DATOS PARA K-MEANS\n";
echo "====================================\n\n";

// 1. CHOFERES
echo "📊 CHOFERES\n";
echo "----------------\n";
$choferes = Chofer::all();
echo "Total de choferes: " . $choferes->count() . "\n";
if ($choferes->count() > 0) {
    echo "\nPrimeros 5 choferes:\n";
    foreach ($choferes->take(5) as $chofer) {
        echo "  - ID: {$chofer->id}, Nombre: {$chofer->nombre} {$chofer->apellidos}\n";
    }
}
echo "\n";

// 2. VIAJES
echo "📊 VIAJES\n";
echo "----------------\n";
$viajes = Viaje::all();
echo "Total de viajes: " . $viajes->count() . "\n";

$viajesPorEstado = Viaje::selectRaw('estado, COUNT(*) as total')
    ->groupBy('estado')
    ->get();

echo "\nViajes por estado:\n";
foreach ($viajesPorEstado as $item) {
    echo "  - {$item->estado}: {$item->total}\n";
}

if ($viajes->count() > 0) {
    echo "\nÚltimos 5 viajes:\n";
    foreach (Viaje::latest()->take(5)->get() as $viaje) {
        echo "  - ID: {$viaje->id}, Chofer: {$viaje->chofer_id}, Estado: {$viaje->estado}, Fecha: " . ($viaje->fecha_viaje ?? 'N/A') . "\n";
    }
}
echo "\n";

// 3. CONFIRMACIONES
echo "📊 CONFIRMACIONES DE VIAJE\n";
echo "----------------\n";
$confirmaciones = ConfirmacionViaje::all();
echo "Total de confirmaciones: " . $confirmaciones->count() . "\n";

$confirmacionesPorEstado = ConfirmacionViaje::selectRaw('estado, COUNT(*) as total')
    ->groupBy('estado')
    ->get();

echo "\nConfirmaciones por estado:\n";
foreach ($confirmacionesPorEstado as $item) {
    echo "  - {$item->estado}: {$item->total}\n";
}

if ($confirmaciones->count() > 0) {
    echo "\nÚltimas 5 confirmaciones:\n";
    foreach (ConfirmacionViaje::latest()->take(5)->get() as $conf) {
        echo "  - ID: {$conf->id}, Viaje: {$conf->viaje_id}, Hijo: {$conf->hijo_id}, Estado: {$conf->estado}\n";
    }
}
echo "\n";

// 4. ASISTENCIAS
echo "📊 ASISTENCIAS\n";
echo "----------------\n";
$asistencias = Asistencia::all();
echo "Total de asistencias: " . $asistencias->count() . "\n";

$asistenciasPorEstado = Asistencia::selectRaw('estado, COUNT(*) as total')
    ->groupBy('estado')
    ->get();

echo "\nAsistencias por estado:\n";
foreach ($asistenciasPorEstado as $item) {
    echo "  - {$item->estado}: {$item->total}\n";
}

if ($asistencias->count() > 0) {
    echo "\nÚltimas 5 asistencias:\n";
    foreach (Asistencia::latest()->take(5)->get() as $asist) {
        echo "  - ID: {$asist->id}, Viaje: {$asist->viaje_id}, Hijo: {$asist->hijo_id}, Estado: {$asist->estado}\n";
    }
}
echo "\n";

// 5. ANÁLISIS DE VIAJES CON DATOS COMPLETOS
echo "📊 ANÁLISIS DE VIAJES CON DATOS COMPLETOS\n";
echo "----------------\n";

$viajesConDatos = Viaje::withCount(['confirmaciones', 'asistencias'])
    ->having('confirmaciones_count', '>', 0)
    ->get();

echo "Viajes con confirmaciones: " . $viajesConDatos->count() . "\n";

if ($viajesConDatos->count() > 0) {
    echo "\nEjemplo de viajes con datos:\n";
    foreach ($viajesConDatos->take(10) as $viaje) {
        $tasaAsistencia = $viaje->confirmaciones_count > 0 
            ? round(($viaje->asistencias_count / $viaje->confirmaciones_count) * 100, 2)
            : 0;
        
        echo sprintf(
            "  - Viaje #%d (Chofer: %d): %d confirmaciones, %d asistencias (%.2f%% tasa)\n",
            $viaje->id,
            $viaje->chofer_id,
            $viaje->confirmaciones_count,
            $viaje->asistencias_count,
            $tasaAsistencia
        );
    }
}
echo "\n";

// 6. ANÁLISIS POR CHOFER
echo "📊 ANÁLISIS POR CHOFER\n";
echo "----------------\n";

$choferesSummary = \DB::table('viajes as v')
    ->select(
        'v.chofer_id',
        'c.nombre',
        'c.apellidos',
        \DB::raw('COUNT(DISTINCT v.id) as total_viajes'),
        \DB::raw('COUNT(DISTINCT cv.id) as total_confirmaciones'),
        \DB::raw('COUNT(DISTINCT a.id) as total_asistencias')
    )
    ->leftJoin('choferes as c', 'c.id', '=', 'v.chofer_id')
    ->leftJoin('confirmaciones_viaje as cv', 'cv.viaje_id', '=', 'v.id')
    ->leftJoin('asistencias as a', 'a.viaje_id', '=', 'v.id')
    ->groupBy('v.chofer_id', 'c.nombre', 'c.apellidos')
    ->having('total_viajes', '>', 0)
    ->get();

echo "Choferes con viajes registrados: " . $choferesSummary->count() . "\n\n";

foreach ($choferesSummary as $chofer) {
    $tasaAsistencia = $chofer->total_confirmaciones > 0
        ? round(($chofer->total_asistencias / $chofer->total_confirmaciones) * 100, 2)
        : 0;
    
    echo sprintf(
        "Chofer #%d - %s %s:\n  Viajes: %d | Confirmaciones: %d | Asistencias: %d | Tasa: %.2f%%\n\n",
        $chofer->chofer_id,
        $chofer->nombre ?? 'N/A',
        $chofer->apellidos ?? '',
        $chofer->total_viajes,
        $chofer->total_confirmaciones,
        $chofer->total_asistencias,
        $tasaAsistencia
    );
}

// 7. RECOMENDACIONES
echo "\n====================================\n";
echo "📋 RECOMENDACIONES PARA K-MEANS\n";
echo "====================================\n\n";

if ($viajes->count() == 0) {
    echo "⚠️  NO HAY VIAJES REGISTRADOS\n";
    echo "   - Crear viajes de prueba para poder hacer análisis\n";
} elseif ($confirmaciones->count() == 0) {
    echo "⚠️  NO HAY CONFIRMACIONES REGISTRADAS\n";
    echo "   - Crear confirmaciones para los viajes existentes\n";
} elseif ($asistencias->count() == 0) {
    echo "⚠️  NO HAY ASISTENCIAS REGISTRADAS\n";
    echo "   - Registrar asistencias para poder calcular métricas\n";
} elseif ($viajesConDatos->count() < 10) {
    echo "⚠️  DATOS INSUFICIENTES\n";
    echo "   - Solo hay {$viajesConDatos->count()} viajes con datos completos\n";
    echo "   - Recomendado: Al menos 20-50 viajes para análisis K-means significativo\n";
} else {
    echo "✅ HAY SUFICIENTES DATOS PARA K-MEANS\n";
    echo "   - {$viajesConDatos->count()} viajes con datos completos\n";
    echo "   - {$choferesSummary->count()} choferes con actividad\n";
    echo "   - El análisis puede usar datos reales\n";
}

echo "\n====================================\n";
echo "ESTRUCTURA ESPERADA PARA K-MEANS:\n";
echo "====================================\n\n";

echo "SELECT \n";
echo "  v.id as viaje_id,\n";
echo "  v.chofer_id,\n";
echo "  COUNT(DISTINCT cv.id) as total_confirmaciones,\n";
echo "  COUNT(DISTINCT a.id) as total_asistencias,\n";
echo "  (COUNT(DISTINCT a.id)::float / NULLIF(COUNT(DISTINCT cv.id), 0) * 100) as tasa_asistencia,\n";
echo "  -- Otras métricas calculadas...\n";
echo "FROM viajes v\n";
echo "LEFT JOIN confirmaciones_viaje cv ON cv.viaje_id = v.id\n";
echo "LEFT JOIN asistencias a ON a.viaje_id = v.id\n";
echo "GROUP BY v.id, v.chofer_id\n\n";

echo "====================================\n\n";

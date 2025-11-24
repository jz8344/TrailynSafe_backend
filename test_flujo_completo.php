<?php

/**
 * Script de Prueba: Flujo Completo de Viajes con K-Means
 * 
 * Este script verifica el flujo completo del sistema:
 * 1. Admin crea viaje (estado: pendiente)
 * 2. Admin programa viaje manualmente (estado: programado)
 * 3. Chofer abre confirmaciones (estado: en_confirmaciones)
 * 4. Padres confirman (solo si estado = en_confirmaciones)
 * 5. Chofer cierra confirmaciones (estado: confirmado)
 * 6. Chofer confirma viaje (llama k-Means, estado: generando_ruta)
 * 7. Django envía resultado (estado: ruta_generada)
 * 8. Chofer inicia viaje (estado: en_curso)
 * 9. Chofer finaliza viaje (estado: finalizado)
 */

require __DIR__ . '/vendor/autoload.php';

use Illuminate\Support\Facades\DB;

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n========================================\n";
echo "PRUEBA DE FLUJO COMPLETO - TRALYNSAFE\n";
echo "========================================\n\n";

try {
    // 1. Buscar un viaje de prueba
    $viaje = DB::table('viajes')
        ->where('id', 2) // Viaje ID 2 (jncsdf)
        ->first();
    
    if (!$viaje) {
        echo "❌ No se encontró el viaje ID 2\n";
        exit(1);
    }
    
    echo "✅ Viaje encontrado:\n";
    echo "   ID: {$viaje->id}\n";
    echo "   Nombre: {$viaje->nombre}\n";
    echo "   Estado actual: {$viaje->estado}\n";
    echo "   Escuela ID: {$viaje->escuela_id}\n";
    echo "   Chofer ID: {$viaje->chofer_id}\n";
    echo "   Tipo: {$viaje->tipo_viaje}\n\n";
    
    // 2. Verificar chofer
    $chofer = DB::table('choferes')->where('id', $viaje->chofer_id)->first();
    if ($chofer) {
        echo "✅ Chofer asignado:\n";
        echo "   Nombre: {$chofer->nombre} {$chofer->apellidos}\n";
        echo "   Email: {$chofer->correo}\n\n";
    }
    
    // 3. Verificar escuela
    $escuela = DB::table('escuelas')->where('id', $viaje->escuela_id)->first();
    if ($escuela) {
        echo "✅ Escuela destino:\n";
        echo "   Nombre: {$escuela->nombre}\n";
        echo "   Dirección: {$escuela->direccion}\n";
        
        if (isset($escuela->latitud) && isset($escuela->longitud) && $escuela->latitud && $escuela->longitud) {
            echo "   Coordenadas: {$escuela->latitud}, {$escuela->longitud}\n";
        } else {
            echo "   ⚠️  Faltan coordenadas de la escuela (necesarias para k-Means)\n";
        }
        echo "\n";
    }
    
    // 4. Verificar confirmaciones
    $confirmaciones = DB::table('confirmaciones_viajes')
        ->where('viaje_id', $viaje->id)
        ->where('estado', 'confirmado')
        ->get();
    
    echo "📋 Confirmaciones registradas: " . $confirmaciones->count() . "\n";
    
    if ($confirmaciones->count() > 0) {
        echo "\nDetalles de confirmaciones:\n";
        foreach ($confirmaciones as $conf) {
            $hijo = DB::table('hijos')->where('id', $conf->hijo_id)->first();
            echo "   - Hijo: {$hijo->nombre}\n";
            echo "     Dirección: {$conf->direccion_recogida}\n";
            echo "     Coordenadas: {$conf->latitud}, {$conf->longitud}\n\n";
        }
    } else {
        echo "   ⚠️  No hay confirmaciones aún\n\n";
    }
    
    // 5. Verificar ruta generada
    $ruta = DB::table('rutas')->where('viaje_id', $viaje->id)->first();
    
    if ($ruta) {
        echo "✅ Ruta generada:\n";
        echo "   ID Ruta: {$ruta->id}\n";
        echo "   Distancia total: {$ruta->distancia_total_km} km\n";
        echo "   Tiempo estimado: {$ruta->tiempo_estimado_minutos} min\n";
        
        $paradas = DB::table('paradas_ruta')
            ->where('ruta_id', $ruta->id)
            ->orderBy('orden')
            ->get();
        
        echo "   Paradas: " . $paradas->count() . "\n\n";
        
        if ($paradas->count() > 0) {
            echo "Detalle de paradas:\n";
            foreach ($paradas as $parada) {
                echo "   {$parada->orden}. {$parada->direccion}\n";
                echo "      Lat: {$parada->latitud}, Long: {$parada->longitud}\n";
                echo "      ETA: {$parada->tiempo_estimado_minutos} min\n\n";
            }
        }
    } else {
        echo "⚠️  No hay ruta generada aún\n\n";
    }
    
    // 6. Resumen del flujo
    echo "========================================\n";
    echo "FLUJO ESPERADO DEL SISTEMA\n";
    echo "========================================\n\n";
    
    $estados = [
        'pendiente' => 'Admin crea viaje',
        'programado' => 'Admin programa viaje manualmente',
        'en_confirmaciones' => 'Chofer abre ventana de confirmaciones',
        'confirmado' => 'Chofer cierra confirmaciones',
        'generando_ruta' => 'Sistema genera ruta con k-Means',
        'ruta_generada' => 'Ruta lista para iniciar',
        'en_curso' => 'Viaje en progreso',
        'finalizado' => 'Viaje completado',
        'cancelado' => 'Viaje cancelado'
    ];
    
    echo "Estado actual: {$viaje->estado}\n\n";
    
    echo "Estados del flujo:\n";
    foreach ($estados as $estado => $descripcion) {
        $check = ($viaje->estado === $estado) ? '👉 ' : '   ';
        echo "{$check}[{$estado}] - {$descripcion}\n";
    }
    
    echo "\n========================================\n";
    echo "ACCIONES DISPONIBLES POR ROL\n";
    echo "========================================\n\n";
    
    switch ($viaje->estado) {
        case 'pendiente':
            echo "✅ ADMIN: Puede cambiar estado a 'programado' en el formulario Vue\n";
            echo "   Endpoint: PUT /api/admin/viajes/{id} (campo estado = 'programado')\n";
            break;
            
        case 'programado':
            echo "✅ CHOFER: Puede abrir confirmaciones\n";
            echo "   Endpoint: POST /api/chofer/viajes/{id}/abrir-confirmaciones\n";
            echo "   Estado siguiente: en_confirmaciones\n";
            break;
            
        case 'en_confirmaciones':
            echo "✅ PADRES: Pueden confirmar ubicaciones de recogida\n";
            echo "   Endpoint: POST /api/viajes/{id}/confirmar\n";
            echo "   Payload: hijo_id, direccion_recogida, latitud, longitud, referencia\n\n";
            echo "✅ CHOFER: Puede cerrar confirmaciones cuando haya cupo mínimo\n";
            echo "   Endpoint: POST /api/chofer/viajes/{id}/cerrar-confirmaciones\n";
            echo "   Cupo mínimo: {$viaje->cupo_minimo}\n";
            echo "   Confirmaciones actuales: {$viaje->confirmaciones_actuales}\n";
            break;
            
        case 'confirmado':
            echo "✅ CHOFER: Puede confirmar viaje y generar ruta con k-Means\n";
            echo "   Endpoint: POST /api/chofer/viajes/{id}/confirmar-viaje\n";
            echo "   Esto llamará a Django para generar ruta optimizada\n";
            break;
            
        case 'generando_ruta':
            echo "⏳ Sistema generando ruta con k-Means + TSP...\n";
            echo "   Django procesando confirmaciones\n";
            echo "   Esperando webhook en: /api/webhook/ruta-generada\n";
            break;
            
        case 'ruta_generada':
            echo "✅ CHOFER: Puede iniciar el viaje\n";
            echo "   Ver ruta en app móvil\n";
            echo "   Cambiar estado a 'en_curso' al comenzar\n";
            break;
            
        case 'en_curso':
            echo "✅ CHOFER: Viaje en progreso\n";
            echo "   Seguir ruta en tiempo real\n";
            echo "   Escanear QR en cada parada\n";
            break;
            
        case 'finalizado':
            echo "✅ Viaje completado exitosamente\n";
            break;
            
        case 'cancelado':
            echo "❌ Viaje cancelado\n";
            break;
    }
    
    echo "\n========================================\n";
    echo "IMPORTANTE PARA APP PADRES\n";
    echo "========================================\n\n";
    echo "❗ Los padres SOLO pueden ver viajes en estado 'en_confirmaciones'\n";
    echo "❗ NO pueden confirmar si el viaje está en 'programado'\n";
    echo "❗ Endpoint viajesDisponibles filtra: estado = 'en_confirmaciones'\n";
    echo "❗ Endpoint confirmar valida: estado debe ser 'en_confirmaciones'\n\n";
    
    echo "========================================\n";
    echo "URLs DE ENDPOINTS CLAVE\n";
    echo "========================================\n\n";
    echo "Backend Laravel: " . env('APP_URL', 'http://localhost:8000') . "\n";
    echo "Backend Django: https://backend-django-production-6d57.up.railway.app\n";
    echo "Admin Frontend: " . env('FRONTEND_URL', 'http://localhost:5173') . "\n\n";
    
    echo "✅ Verificación completa\n\n";
    
} catch (\Exception $e) {
    echo "\n❌ Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
    exit(1);
}

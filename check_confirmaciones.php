<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\ConfirmacionViaje;

echo "Consultando confirmaciones del viaje 4...\n\n";

$confirmaciones = ConfirmacionViaje::where('viaje_id', 4)
    ->where('estado', 'confirmado')
    ->get();

echo "Total confirmaciones confirmadas: " . $confirmaciones->count() . "\n\n";

foreach ($confirmaciones as $conf) {
    echo "Confirmación ID: {$conf->id}\n";
    echo "  Hijo ID: {$conf->hijo_id}\n";
    echo "  Latitud: " . ($conf->latitud ?? 'NULL') . "\n";
    echo "  Longitud: " . ($conf->longitud ?? 'NULL') . "\n";
    echo "  Dirección: {$conf->direccion_recogida}\n";
    echo "  Estado: {$conf->estado}\n";
    echo "  ---\n";
}

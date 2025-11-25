<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\GeocodingService;

echo "Testing GeocodingService...\n\n";

$service = new GeocodingService();

$direccion = "Cjon. Zúñiga S/N, 45660 San Miguel Cuyutlán, Jal., México";
echo "Direccion: {$direccion}\n";

$resultado = $service->geocodificar($direccion);

if ($resultado) {
    echo "✅ Exito!\n";
    print_r($resultado);
} else {
    echo "❌ Fallo\n";
    
    // Ver logs
    echo "\nUltimos logs:\n";
    $logs = file_get_contents('storage/logs/laravel.log');
    $lines = explode("\n", $logs);
    $lastLines = array_slice($lines, -20);
    echo implode("\n", $lastLines);
}
